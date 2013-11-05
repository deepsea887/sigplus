<?php
/**
* @file
* @brief    sigplus Image Gallery Plus image generation
* @author   Levente Hunyadi
* @version  $__VERSION__$
* @remarks  Copyright (C) 2009-2011 Levente Hunyadi
* @remarks  Licensed under GNU/GPLv3, see http://www.gnu.org/licenses/gpl-3.0.html
* @see      http://hunyadi.info.hu/projects/sigplus
*/

/*
* sigplus Image Gallery Plus plug-in for Joomla
* Copyright 2009-2010 Levente Hunyadi
*
* sigplus is free software: you can redistribute it and/or modify
* it under the terms of the GNU General Public License as published by
* the Free Software Foundation, either version 3 of the License, or
* (at your option) any later version.
*
* sigplus is distributed in the hope that it will be useful,
* but WITHOUT ANY WARRANTY; without even the implied warranty of
* MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
* GNU General Public License for more details.
*
* You should have received a copy of the GNU General Public License
* along with this program.  If not, see <http://www.gnu.org/licenses/>.
*/

// no direct access
defined( '_JEXEC' ) or die( 'Restricted access' );

require_once dirname(__FILE__).DIRECTORY_SEPARATOR.'filesystem.php';
require_once dirname(__FILE__).DIRECTORY_SEPARATOR.'librarian.php';

class SIGPlusImageLibrary {
	/**
	* Available memory computed from total memory and memory usage.
	*/
	protected static function memory_get_available() {
		static $limit = null;  // value of php.ini configuration directive memory_limit in bytes
		if (!isset($limit)) {
			$inilimit = trim(ini_get('memory_limit'));
			if (empty($inilimit)) {  // no limit set
				$limit = false;
			} elseif (is_numeric($inilimit)) {
				$limit = (int) $inilimit;
			} else {
				$limit = (int) substr($inilimit, 0, -1);
				switch (strtolower(substr($inilimit, -1))) {
					case 'g':
						$limit *= 1024;
					case 'm':
						$limit *= 1024;
					case 'k':
						$limit *= 1024;
				}
			}
		}

		if ($limit !== false) {
			if ($limit < 0) {
				return false;  // no memory upper limit set in php.ini
			} else {
				return $limit - memory_get_usage(true);
			}
		} else {
			return false;
		}
	}

	/**
	* Generates a thumbnail image for an original image.
	*/
	public function createThumbnail($imagepath, $thumbpath, $thumb_w, $thumb_h, $crop = true, $quality = 85) {
		throw new SIGPlusLibraryUnavailableException();
	}

	/**
	* Generates a watermarked image for an original image.
	* @param string $imagepath The full path to the image to place a watermark into.
	* @param string $watermarkpath The full path to the image to use as a watermark.
	* @param string $watermarkedimagepath The full path where the watermarked image should be written.
	*/
	public function createWatermarked($imagepath, $watermarkpath, $watermarkedimagepath, $params) {
		throw new SIGPlusLibraryUnavailableException();
	}

	public static function instantiate($library) {
		if ($library == 'default') {
			if (is_gmagick_supported()) {
				$library = 'gmagick';
			} elseif (is_imagick_supported()) {
				$library = 'imagick';
			} elseif (is_gd_supported()) {
				$library = 'gd';
			} else {
				$library = 'none';
			}
		}
		switch ($library) {
			case 'gmagick':
				if (is_gmagick_supported()) {
					return new SIGPlusImageLibraryGmagick();
				}
				break;
			case 'imagick':
				if (is_imagick_supported()) {
					return new SIGPlusImageLibraryImagick();
				}
				break;
			case 'gd':
				if (is_gd_supported()) {
					return new SIGPlusImageLibraryGD();
				}
				break;
		}
		return new SIGPlusImageLibrary();  // all operations will throw an image library unavailable exception
	}

	/**
	* Checks whether sufficient memory is available to load and process an image.
	*/
	protected function checkMemory($imagepath) {
		$memory_available = self::memory_get_available();
		if ($memory_available !== false) {
			$imagedata = fsx::getimagesize($imagepath);
			if ($imagedata === false) {
				return;
			}
			if (!isset($imagedata['channels'])) {  // assume RGB (i.e. 3 channels)
				$imagedata['channels'] = 3;
			}
			if (!isset($imagedata['bits'])) {  // assume 8 bits per channel
				$imagedata['bits'] = 8;
			}

			$memory_required = (int)ceil($imagedata[0] * $imagedata[1] * $imagedata['channels'] * $imagedata['bits'] / 8);

			$safety_factor = 1.8;  // not all available memory can be consumed in order to ensure safe operations, safety factor is an empirical value
			if ($safety_factor * $memory_required >= $memory_available) {
				throw new SIGPlusOutOfMemoryException($memory_required, $memory_available, $imagepath);
			}
		}
	}

	protected function computeCoordinates($params, $width, $height, $w, $h) {
		$position = isset($params['position']) ? $params['position'] : false;
		$x = isset($params['x']) ? $params['x'] : 0;
		$y = isset($params['y']) ? $params['y'] : 0;
		$centerx = floor(($width - $w) / 2);
		$centery = floor(($height - $h) / 2);
		switch ($position) {
			case 'nw': break;
			case 'n':  $x = $centerx; break;
			case 'ne': $x = $width - $w - $x; break;
			case 'w':  $y = $centery; break;
			case 'c':  $x = $centerx; $y = $centery; break;
			case 'e':  $y = $centery; $x = $width - $w - $x; break;
			case 'sw': $y = $height - $h - $y; break;
			case 's':  $x = $centerx; $y = $height - $h - $y; break;
			case 'se': $x = $width - $w - $x; $y = $height - $h - $y; break;
			default:   $y = $height - $h - $y; break;
		}
		return array($x, $y);
	}
}

class SIGPlusImageLibraryGD extends SIGPlusImageLibrary {
	/**
	* Creates an in-memory image from a local or remote image.
	* @param string $imagepath The absolute path to a local image or the URL to a remote image.
	*/
	private static function imageFromFile($imagepath) {
		$ext = strtolower(pathinfo($imagepath, PATHINFO_EXTENSION));
		switch ($ext) {
			case 'jpg': case 'jpeg':
				return fsx::imagecreatefromjpeg($imagepath);
			case 'gif':
				return fsx::imagecreatefromgif($imagepath);
			case 'png':
				return fsx::imagecreatefrompng($imagepath);
			default:
				return false;  // missing or unrecognized extension
		}
	}

	/**
	* Exports an in-memory image to a local image file.
	* @param string $imagepath The absolute path to a local image.
	* @param image $image In-memory image to export.
	* @param int $quality Quality measure between 0 and 100 for JPEG compression.
	*/
	private static function imageToFile($imagepath, $image, $quality) {
		$ext = strtolower(pathinfo($imagepath, PATHINFO_EXTENSION));
		switch ($ext) {
			case 'jpg': case 'jpeg':
				return fsx::imagejpeg($image, $imagepath, $quality);
			case 'gif':
				return fsx::imagegif($image, $imagepath);
			case 'png':
				return fsx::imagepng($image, $imagepath, 9);
			default:
				return false;  // missing or unrecognized extension
		}
	}

	/**
	* Determines whether an image is an animated GIF image.
	*/
	private static function isAnimated($imagepath) {
		if ('gif' != strtolower(pathinfo($imagepath, PATHINFO_EXTENSION))) {
			return false;  // only GIF format supports animation
		} else {
			return (bool)preg_match('/\x00\x21\xF9\x04.{4}\x00(\x2C|\x21)/s', file_get_contents($imagepath));
		}
	}
	
	public function createThumbnail($imagepath, $thumbpath, $thumb_w, $thumb_h, $crop = true, $quality = 85) {
		// check memory requirement for operation
		$this->checkMemory($imagepath);

		if (self::isAnimated($imagepath)) {
			// get GIF animation sequence
			$gifDecoder = new SIGPlusGifDecoder(fsx::file_get_contents($imagepath));

			// re-scale each frame of the animated image
			$target_frames = array();
			foreach ($gifDecoder->GIFGetFrames() as $source_frame) {
				// convert string data into an image resource
				$source_image = imagecreatefromstring($source_frame);

				// re-scale a single frame
				$target_image = $this->createThumbnailFromResource($source_image, $thumb_w, $thumb_h, $crop, $quality);

				// convert image resource into a string
				ob_start();				
				imagegif($target_image);
				$target_frames[] = ob_get_clean();
				
				// release image resources
				imagedestroy($source_image);
				imagedestroy($target_image);
			}

			// build an animated frames array from separate frames
			$gifEncoder = new SIGPlusGifEncoder(
				$target_frames,
				$gifDecoder->GIFGetDelays(), $gifDecoder->GIFGetLoop(), $gifDecoder->GIFGetDisposal(),
				$gifDecoder->GIFGetTransparentR(), $gifDecoder->GIFGetTransparentG(), $gifDecoder->GIFGetTransparentB()
			);

			// save the animation in a single file
			fsx::file_put_contents($thumbpath, $gifEncoder->GetAnimation());
		} else {
			// load image from file
			$source_img = self::imageFromFile($imagepath);
			if (!$source_img) {
				return false;  // could not create image from file
			}
		
			// process image
			$thumb_img = $this->createThumbnailFromResource($source_img, $thumb_w, $thumb_h, $crop, $quality);
			imagedestroy($source_img);
			if (!$thumb_img) {
				return false;
			}
			
			// save image to file
			$result = self::imageToFile($thumbpath, $thumb_img, $quality);
			imagedestroy($thumb_img);
			return $result;
		}
	}

	/**
	* Creates a thumbnail image from a source image.
	*
	* @param $source_img The image resource to serve as source.
	* @param $thumb_w The desired thumbnail width.
	* @param $thumb_h The desired thumbnail height.
	* @parap $crop Whether to crop images or re-scale them.
	*/
	private function createThumbnailFromResource($source_img, $thumb_w, $thumb_h, $crop = true, $quality = 85) {
		// get dimensions for cropping and resizing
		$orig_w = imagesx($source_img);
		$orig_h = imagesy($source_img);
		if (false && $thumb_w >= $orig_w && $thumb_h >= $orig_h) {  // nothing to do
			$thumb_img = $source_img;
		} else {
			$ratio_orig = $orig_w/$orig_h;  // width-to-height ratio of original image
			if ($crop) {  // resize with automatic centering, crop image if necessary
				if ($thumb_w == 0 || $thumb_h == 0) {
					throw new Exception('Both width and height must be specified when images are rescaled with cropping enabled.');
				}

				$ratio_thumb = $thumb_w/$thumb_h;  // width-to-height ratio of thumbnail image
				if ($ratio_thumb > $ratio_orig) {  // crop top and bottom
					$zoom = $orig_w / $thumb_w;  // zoom factor of original image w.r.t. thumbnail
					$crop_h = floor($zoom * $thumb_h);
					$crop_w = $orig_w;
					$crop_x = 0;
					$crop_y = floor(0.5 * ($orig_h - $crop_h));
				} else {  // crop left and right
					$zoom = $orig_h / $thumb_h;  // zoom factor of original image w.r.t. thumbnail
					$crop_h = $orig_h;
					$crop_w = floor($zoom * $thumb_w);
					$crop_x = floor(0.5 * ($orig_w - $crop_w));
					$crop_y = 0;
				}
			} else {  // resize with fitting larger dimension, do not crop image
				$crop_w = $orig_w;
				$crop_h = $orig_h;
				$crop_x = 0;
				$crop_y = 0;
				if ($thumb_w == 0) {  // width unspecified
					$zoom = $orig_h / $thumb_h;
					$thumb_w = floor($orig_w / $zoom);
				} elseif ($thumb_h == 0) {  // height unspecified
					$zoom = $orig_w / $thumb_w;
					$thumb_h = floor($orig_h / $zoom);
				} elseif ($thumb_w/$thumb_h > $ratio_orig) {  // fit height
					$zoom = $orig_h / $thumb_h;
					$thumb_w = floor($orig_w / $zoom);
				} else {  // fit width
					$zoom = $orig_w / $thumb_w;
					$thumb_h = floor($orig_h / $zoom);
				}

				// formula above may produce zero width or height for extremely narrow and extremely elongated images
				if ($thumb_w < 1) {
					$thumb_w = 1;  // any image must be at least 1px wide
				}
				if ($thumb_h < 1) {
					$thumb_h = 1;  // any image must be at least 1px tall
				}
			}

			$thumb_img = imagecreatetruecolor($thumb_w, $thumb_h);
			$result = imagealphablending($thumb_img, false) && imagesavealpha($thumb_img, true);

			if (!imageistruecolor($source_img) && ($transparentindex = imagecolortransparent($source_img)) >= 0) {
				// convert color index transparency to alpha channel transparency
				if (imagecolorstotal($source_img) > $transparentindex) {  // transparent color is in palette
					$transparentrgba = imagecolorsforindex($source_img, $transparentindex);
				} else {  // use white as transparent background color
					$transparentrgba = array('red' => 255, 'green' => 255, 'blue' => 255);
				}

				// fill image with transparent color
				$transparentcolor = imagecolorallocatealpha($thumb_img, $transparentrgba['red'], $transparentrgba['green'], $transparentrgba['blue'], 127);
				imagefilledrectangle($thumb_img, 0, 0, $orig_w, $orig_h, $transparentcolor);
				imagecolordeallocate($thumb_img, $transparentcolor);
			}

			// resample image into thumbnail size
			$result = $result && imagecopyresampled($thumb_img, $source_img, 0, 0, $crop_x, $crop_y, $thumb_w, $thumb_h, $crop_w, $crop_h);

			if ($result === false) {
				imagedestroy($thumb_img);
				return false;
			}
		}

		return $thumb_img;
	}

	public function createWatermarked($imagepath, $watermarkpath, $watermarkedimagepath, $params) {
		// check memory requirement for operation
		$this->checkMemory($imagepath);

		// load watermark image
		$watermark_img = self::imageFromFile($watermarkpath);
		if (!$watermark_img) {
			return false;  // could not create image from file
		}

		// load image
		$source_img = self::imageFromFile($imagepath);
		if (!$source_img) {
			return false;  // could not create image from file
		}

		$width = imagesx($source_img);
		$height = imagesy($source_img);
		$w = imagesx($watermark_img);
		$h = imagesy($watermark_img);
		list($x, $y) = $this->computeCoordinates($params, $width, $height, $w, $h);

		imagecopy($source_img, $watermark_img, $x, $y, 0, 0, $w, $h);
		imagedestroy($watermark_img);

		$result = self::imageToFile($watermarkedimagepath, $source_img, isset($params['quality']) ? $params['quality'] : 85);
		imagedestroy($source_img);
		return $result;
	}
}

class SIGPlusImageLibraryImagick extends SIGPlusImageLibrary {
	private static function isAnimated($image) {
		$frames = 0;
		foreach ($image as $i) {
			$frames++;
			if ($frames > 1) {
				return true;
			}
		}
		return false;
	}

	public function createThumbnail($imagepath, $thumbpath, $thumb_w, $thumb_h, $crop = true, $quality = 85) {
		$image = new Imagick($imagepath);
		if (self::isAnimated($image)) {
			// loop through the frames
			foreach ($image as $frame) {
				if ($crop) {  // resize with automatic centering, crop frame if necessary
					$frame->cropThumbnailImage($thumb_w, $thumb_h);
				} else {  // resize with fitting larger dimension, do not crop frame
					$frame->thumbnailImage($thumb_w, $thumb_h, true);
				}
				$frame->setImagePage($thumb_w, $thumb_h, 0, 0);
			}

			// write animated image to disk
			$result = $image->writeImages($thumbpath);
		} else {
			// resize standard (non-animated) image
			$image->setImageCompressionQuality($quality);
			if ($crop) {  // resize with automatic centering, crop image if necessary
				$image->cropThumbnailImage($thumb_w, $thumb_h);
			} else {  // resize with fitting larger dimension, do not crop image
				$image->thumbnailImage($thumb_w, $thumb_h, true);
			}

			// write standard image to disk
			$result = $image->writeImage($thumbpath);
		}
		$image->destroy();
		return $result;
	}

	public function createWatermarked($imagepath, $watermarkpath, $watermarkedimagepath, $params) {
		if (!is_file($watermarkpath)) {
			return false;
		}

		$image = new Imagick($imagepath);
		$geometry = $image->getImageGeometry();
		$width = $geometry['width'];
		$height = $geometry['height'];

		$watermark = new Imagick($watermarkpath);
		$geometry = $watermark->getImageGeometry();
		$w = $geometry['width'];
		$h = $geometry['height'];

		list($x, $y) = $this->computeCoordinates($params, $width, $height, $w, $h);

		$image->compositeImage($watermark, Imagick::COMPOSITE_DEFAULT, $x, $y);
		$result = $image->writeImage($watermarkedimagepath);

		$watermark->destroy();
		$image->destroy();
		return $result;
	}
}

class SIGPlusImageLibraryGmagick extends SIGPlusImageLibrary {
	public function createThumbnail($imagepath, $thumbpath, $thumb_w, $thumb_h, $crop = true, $quality = 85) {
		$image = new Gmagick($imagepath);
		if ($crop) {  // resize with automatic centering, crop image if necessary
			$image->cropThumbnailImage($thumb_w, $thumb_h);
		} else {  // resize with fitting larger dimension, do not crop image
			$image->thumbnailImage($thumb_w, $thumb_h, true);
		}
		$result = $image->writeImage($thumbpath);
		$image->destroy();
		return $result;
	}

	public function createWatermarked($imagepath, $watermarkpath, $watermarkedimagepath, $params) {
		if (!is_file($watermarkpath)) {
			return false;
		}

		$image = new Gmagick($imagepath);
		$width = $image->getImageWidth();
		$height = $image->getImageHeight();

		$watermark = new Gmagick($watermarkpath);
		$w = $watermark->getImageWidth();
		$h = $watermark->getImageHeight();

		list($x, $y) = $this->computeCoordinates($params, $width, $height, $w, $h);

		$image->compositeImage($watermark, Gmagick::COMPOSITE_DEFAULT, $x, $y);
		$result = $image->writeImage($watermarkedimagepath);

		$watermark->destroy();
		$image->destroy();
		return $result;
	}
}

class SIGPlusGifDecoder {
	private $GIF_TransparentR = - 1;
	private $GIF_TransparentG = - 1;
	private $GIF_TransparentB = - 1;
	private $GIF_TransparentI = 0;
	private $GIF_buffer = array();
	private $GIF_arrays = array();
	private $GIF_delays = array();
	private $GIF_dispos = array();
	private $GIF_stream = "";
	private $GIF_string = "";
	private $GIF_bfseek = 0;
	private $GIF_anloop = 0;
	private $GIF_screen = array();
	private $GIF_global = array();
	private $GIF_sorted;
	private $GIF_colorS;
	private $GIF_colorC;
	private $GIF_colorF;

	/**
	* Decodes an animated GIF image into a sequence of frames.
	*
	* @param $GIF_pointer Binary data of an animated GIF image.
	*/
	public function SIGPlusGifDecoder($GIF_pointer) {
		$this->GIF_stream = $GIF_pointer;
		self::GIFGetByte(6);
		self::GIFGetByte(7);
		$this->GIF_screen = $this->GIF_buffer;
		$this->GIF_colorF = $this->GIF_buffer[4] & 0x80 ? 1 : 0;
		$this->GIF_sorted = $this->GIF_buffer[4] & 0x08 ? 1 : 0;
		$this->GIF_colorC = $this->GIF_buffer[4] & 0x07;
		$this->GIF_colorS = 2 << $this->GIF_colorC;
		if ($this->GIF_colorF == 1) {
			self::GIFGetByte(3 * $this->GIF_colorS);
			$this->GIF_global = $this->GIF_buffer;
		}
		for ($cycle = 1; $cycle; ) {
			if (self::GIFGetByte(1)) {
				switch ($this->GIF_buffer[0]) {
				case 0x21:
					self::GIFReadExtensions();
					break;
				case 0x2C:
					self::GIFReadDescriptor();
					break;
				case 0x3B:
					$cycle = 0;
					break;
				}
			} else {
				$cycle = 0;
			}
		}
	}

	private function GIFReadExtensions() {
		self::GIFGetByte(1);
		if ($this->GIF_buffer[0] == 0xff) {
			for (;;) {
				self::GIFGetByte(1);
				if (($u = $this->GIF_buffer[0]) == 0x00) {
					break;
				}
				self::GIFGetByte($u);
				if ($u == 0x03) {
					$this->GIF_anloop = ($this->GIF_buffer[1] | $this->GIF_buffer[2] << 8);
				}
			}
		} else {
			for (;;) {
				self::GIFGetByte(1);
				if (($u = $this->GIF_buffer[0]) == 0x00) {
					break;
				}
				self::GIFGetByte($u);
				if ($u == 0x04) {
					if ($this->GIF_buffer[4] & 0x80) {
						$this->GIF_dispos[] = ($this->GIF_buffer[0] >> 2) - 1;
					} else {
						$this->GIF_dispos[] = ($this->GIF_buffer[0] >> 2) - 0;
					}
					$this->GIF_delays[] = ($this->GIF_buffer[1] | $this->GIF_buffer[2] << 8);
					if ($this->GIF_buffer[3]) {
						$this->GIF_TransparentI = $this->GIF_buffer[3];
					}
				}
			}
		}
	}

	private function GIFReadDescriptor() {
		$GIF_screen = array();
		self::GIFGetByte(9);
		$GIF_screen = $this->GIF_buffer;
		$GIF_colorF = $this->GIF_buffer[8] & 0x80 ? 1 : 0;
		if ($GIF_colorF) {
			$GIF_code = $this->GIF_buffer[8] & 0x07;
			$GIF_sort = $this->GIF_buffer[8] & 0x20 ? 1 : 0;
		} else {
			$GIF_code = $this->GIF_colorC;
			$GIF_sort = $this->GIF_sorted;
		}
		$GIF_size = 2 << $GIF_code;
		$this->GIF_screen[4] &= 0x70;
		$this->GIF_screen[4] |= 0x80;
		$this->GIF_screen[4] |= $GIF_code;
		if ($GIF_sort) {
			$this->GIF_screen[4] |= 0x08;
		}
		if ($this->GIF_TransparentI) {
			$this->GIF_string = 'GIF89a';
		} else {
			$this->GIF_string = 'GIF87a';
		}
		self::GIFPutByte($this->GIF_screen);
		if ($GIF_colorF == 1) {
			self::GIFGetByte(3 * $GIF_size);
			if($this->GIF_TransparentI) {
				$this->GIF_TransparentR = $this->GIF_buffer[3 * $this->GIF_TransparentI + 0];
				$this->GIF_TransparentG = $this->GIF_buffer[3 * $this->GIF_TransparentI + 1];
				$this->GIF_TransparentB = $this->GIF_buffer[3 * $this->GIF_TransparentI + 2];
			}
			self::GIFPutByte($this->GIF_buffer);
		} else {
			if ($this->GIF_TransparentI) {
				$this->GIF_TransparentR = $this->GIF_global[3 * $this->GIF_TransparentI + 0];
				$this->GIF_TransparentG = $this->GIF_global[3 * $this->GIF_TransparentI + 1];
				$this->GIF_TransparentB = $this->GIF_global[3 * $this->GIF_TransparentI + 2];
			}
			self::GIFPutByte($this->GIF_global);
		}
		if ($this->GIF_TransparentI) {
			$this->GIF_string .= "!\xF9\x04\x1\x0\x0".chr($this->GIF_TransparentI)."\x0";
		}
		$this->GIF_string .= chr(0x2C);
		$GIF_screen[8] &= 0x40;
		self::GIFPutByte($GIF_screen);
		self::GIFGetByte(1);
		self::GIFPutByte($this->GIF_buffer);
		for (;;) {
			self::GIFGetByte(1);
			self::GIFPutByte($this->GIF_buffer);
			if (($u = $this->GIF_buffer[0]) == 0x00) {
				break;
			}
			self::GIFGetByte($u);
			self::GIFPutByte($this->GIF_buffer);
		}
		$this->GIF_string .= chr(0x3B);
		$this->GIF_arrays[] = $this->GIF_string;
	}

	private function GIFGetByte($len) {
		$this->GIF_buffer = array();
		for ($i = 0; $i < $len; $i++) {
			if ($this->GIF_bfseek > strlen($this->GIF_stream)) {
				return 0;
			}
			$this->GIF_buffer[] = ord($this->GIF_stream{$this->GIF_bfseek++});  // { and } stand for string indexing
		}
		return 1;
	}

	private function GIFPutByte($bytes) {
		foreach ($bytes as $byte) {
			$this->GIF_string .= chr($byte);
		}
	}

	public function GIFGetFrames() {
		return $this->GIF_arrays;
	}

	public function GIFGetDelays() {
		return $this->GIF_delays;
	}

	public function GIFGetLoop() {
		return $this->GIF_anloop;
	}

	public function GIFGetDisposal() {
		return $this->GIF_dispos;
	}

	public function GIFGetTransparentR() {
		return $this->GIF_TransparentR;
	}

	public function GIFGetTransparentG() {
		return $this->GIF_TransparentG;
	}

	public function GIFGetTransparentB() {
		return $this->GIF_TransparentB;
	}
}

class SIGPlusGifEncoder {
	private $GIF = 'GIF89a';
	private $BUF = array();
	/** The number of times the animation is to be repeated, or 0 to repeat indefinitely. */
	private $LOP = 0;
	/** Disposal. */
	private $DIS = 2;
	/** Transparent color, or -1 for no transparent color. */
	private $COL = -1;
	private $IMG = -1;

	/**
	* Encodes a sequence of frames into an animated GIF image.
	*
	* @param $GIF_src Binary data of image frames, each array element corresponding to a frame.
	* @param $GIF_dly Delay time.
	* @param $GIF_lop The number of times the animation is to be repeated, or 0 to repeat indefinitely.
	* @param $GIF_dis Disposal.
	* @param $GIF_red Red component of transparent color, or -1 for no transparent color.
	* @param $GIF_grn Green component of transparent color, or -1 for no transparent color.
	* @param $GIF_blu Blue component of transparent color, or -1 for no transparent color.
	*/
	public function SIGPlusGifEncoder(array $GIF_src, $GIF_dly, $GIF_lop, $GIF_dis, $GIF_red, $GIF_grn, $GIF_blu) {
			$this->LOP = ($GIF_lop > -1) ? $GIF_lop : 0;
			$this->DIS = ($GIF_dis > -1) ? ($GIF_dis < 3 ? $GIF_dis : 3) : 2;
			$this->COL = ($GIF_red > -1 && $GIF_grn > -1 && $GIF_blu > -1) ? ($GIF_red | ($GIF_grn << 8) | ($GIF_blu << 16)) : -1;
			for ($i = 0; $i < count($GIF_src); $i++) {
				$this->BUF[] = $GIF_src[$i];
				if (substr($this->BUF[$i], 0, 6) != 'GIF87a' && substr($this->BUF[$i], 0, 6) != 'GIF89a') {
					// invalid image format (not a GIF image)
					throw new SIGPlusImageFormatException();
				}
				for ($j = (13 + 3 * (2 << (ord($this->BUF[$i]{10}) & 0x07))), $k = true; $k; $j++) {
					switch ($this->BUF[$i]{$j}) {  // { and } stand for string indexing
					case '!':
						if ((substr($this->BUF[$i], ($j + 3), 8)) == 'NETSCAPE') {
							// already an animated image
							throw new SIGPlusImageFormatException();
						}
						break;
					case ';':
						$k = false;
						break;
					}
				}
			}
			self::GIFAddHeader();
			for ($i = 0; $i < count($this->BUF); $i++) {
				self::GIFAddFrames($i, $GIF_dly[$i]);
			}
			self::GIFAddFooter();
	}

	private function GIFAddHeader() {
		$cmap = 0;
		if (ord($this->BUF[0]{10}) & 0x80) {
			$cmap = 3 * (2 << (ord($this->BUF[0]{10}) & 0x07));  // { and } stand for string indexing
			$this->GIF .= substr($this->BUF[0], 6, 7);
			$this->GIF .= substr($this->BUF[0], 13, $cmap);
			$this->GIF .= "!\377\13NETSCAPE2.0\3\1" . self::GIFWord($this->LOP) . "\0";
		}
	}

	private function GIFAddFrames($i, $d) {
		$Locals_str = 13 + 3 * (2 << (ord($this->BUF[$i]{10}) & 0x07));
		$Locals_end = strlen($this->BUF[$i]) - $Locals_str - 1;
		$Locals_tmp = substr($this->BUF[$i], $Locals_str, $Locals_end);
		$Global_len = 2 << (ord($this->BUF[0]{10}) & 0x07);
		$Locals_len = 2 << (ord($this->BUF[$i]{10}) & 0x07);
		$Global_rgb = substr($this->BUF[0], 13, 3 * (2 << (ord($this->BUF[0]{10}) & 0x07)));
		$Locals_rgb = substr($this->BUF[$i], 13, 3 * (2 << (ord($this->BUF[$i]{10}) & 0x07)));
		$Locals_ext = "!\xF9\x04".chr(($this->DIS << 2) + 0).chr(($d >> 0) & 0xFF).chr(($d >> 8) & 0xFF)."\x0\x0";
		if ($this->COL > - 1 && ord($this->BUF[$i]{10}) & 0x80) {
			for ($j = 0; $j < (2 << (ord($this->BUF[$i]{10}) & 0x07)); $j++) {
				if (ord($Locals_rgb{3 * $j + 0}) == (($this->COL >> 16) & 0xFF) && ord($Locals_rgb{3 * $j + 1}) == (($this->COL >> 8) & 0xFF) && ord($Locals_rgb{3 * $j + 2}) == (($this->COL >> 0) & 0xFF)) {
					$Locals_ext = "!\xF9\x04".chr(($this->DIS << 2) + 1).chr(($d >> 0) & 0xFF).chr(($d >> 8) & 0xFF).chr($j)."\x0";
					break;
				}
			}
		}
		switch($Locals_tmp{0}) {
		case '!' :
			$Locals_img = substr($Locals_tmp, 8, 10);
			$Locals_tmp = substr($Locals_tmp, 18, strlen($Locals_tmp) - 18);
			break;
		case ',' :
			$Locals_img = substr($Locals_tmp, 0, 10);
			$Locals_tmp = substr($Locals_tmp, 10, strlen($Locals_tmp) - 10);
			break;
		}
		if (ord($this->BUF[$i]{10}) & 0x80 && $this->IMG > -1) {
			if ($Global_len == $Locals_len) {
				if (self::GIFBlockCompare($Global_rgb, $Locals_rgb, $Global_len)) {
						$this->GIF .= $Locals_ext.$Locals_img.$Locals_tmp;
				} else {
					$byte = ord($Locals_img{9});
					$byte |= 0x80;
					$byte &= 0xF8;
					$byte |= (ord($this->BUF[0]{10}) & 0x07);
					$Locals_img{9} = chr($byte);
					$this->GIF .= $Locals_ext.$Locals_img.$Locals_rgb.$Locals_tmp;
				}
			} else {
				$byte = ord($Locals_img{9});
				$byte |= 0x80;
				$byte &= 0xF8;
				$byte |= (ord($this->BUF[$i]{10}) & 0x07);
				$Locals_img{9} = chr($byte);
				$this->GIF .= $Locals_ext.$Locals_img.$Locals_rgb.$Locals_tmp;
			}
		} else {
			$this->GIF .= $Locals_ext.$Locals_img.$Locals_tmp;
		}
		$this->IMG = 1;
	}

	private function GIFAddFooter() {
		$this->GIF .= ';';
	}

	private function GIFBlockCompare($GlobalBlock, $LocalBlock, $Len) {
		for ($i = 0; $i < $Len; $i++) {
			if($GlobalBlock{3 * $i + 0} != $LocalBlock{3 * $i + 0} || $GlobalBlock{3 * $i + 1} != $LocalBlock{3 * $i + 1} || $GlobalBlock{3 * $i + 2} != $LocalBlock{3 * $i + 2}) {
				return 0;
			}
		}
		return 1;
	}

	private function GIFWord($int) {
		return chr($int & 0xFF).chr(($int >> 8) & 0xFF);
	}

	public function GetAnimation() {
		return $this->GIF;
	}
}
