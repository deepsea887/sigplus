<?php
/**
* @file
* @brief    sigplus Image Gallery Plus plug-in for Joomla
* @author   Levente Hunyadi
* @version  $__VERSION__$
* @remarks  Copyright (C) 2009-2014 Levente Hunyadi
* @remarks  Licensed under GNU/GPLv3, see http://www.gnu.org/licenses/gpl-3.0.html
* @see      http://hunyadi.info.hu/projects/sigplus
*/

/*
* sigplus Image Gallery Plus plug-in for Joomla
* Copyright 2009-2014 Levente Hunyadi
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

if (!empty($images)) {
	// Provide fall-back to a bare-bone gallery implementation when scripting is disabled in the browser
	//
	// When scripting is enabled, JavaScript code extracts the contents of the <noscript> tag in the initialization
	// phase. The tag <noscript> is wrapped in downlevel-hidden conditional comment so that browsers below IE9 ignore
	// HTML inside, whereas all other browsers interpret it. This is necessary because IE browsers below IE9 cannot
	// access the content of <noscript> elements from JavaScript code, and <noscript> must therefore be omitted.
	// In a similar way, the <noscript> tag must be omitted on mobile platforms where it is fairly unreliable.
	// print '<!--[if gte IE 9]><!--><noscript class="sigplus-gallery"><!--<![endif]-->';

	// Gallery wrapper prologue
	print '<div>';
	print '<div id="'.$galleryid.'" class="'.$gallerystyle.'">';

	// List of images shown directly on the page
	print '<ul>';
	for ($index = 0; $index < $limit; $index++) {
		// no maximum preview image count set or current image index is within maximum limit
		print '<li>';
		$this->printImage($images[$index], $index, $total);
		print '</li>';
	}
	print '</ul>';

	// List of images that appear only in the lightbox pop-up window
	if ($curparams->maxcount > 0 && $curparams->lightbox !== false) {
		// if lightbox is disabled, user cannot navigate to images beyond maximum image count
		for (; $index < $total; $index++) {
			$this->printImage($images[$index], $index, $total, 'display:none !important;');
		}
	}

	// Gallery wrapper epilogue
	print '</div>';
	print '</div>';

	// Provide fall-back to a bare-bone gallery implementation when scripting is disabled in the browser
	// print '<!--[if gte IE 9]><!--></noscript><!--<![endif]-->';
} else {
	print JText::_('SIGPLUS_GALLERY_EMPTY');
}
