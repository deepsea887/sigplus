DROP TABLE IF EXISTS `#__sigplus_data`;
DROP TABLE IF EXISTS `#__sigplus_imageview`;
DROP TABLE IF EXISTS `#__sigplus_image`;
DROP TABLE IF EXISTS `#__sigplus_view`;
DROP TABLE IF EXISTS `#__sigplus_hierarchy`;
DROP TABLE IF EXISTS `#__sigplus_folder`;
DROP TABLE IF EXISTS `#__sigplus_property`;

--
-- Metadata property names.
--
CREATE TABLE `#__sigplus_property` (
	`propertyid` INT UNSIGNED NOT NULL AUTO_INCREMENT,
	`propertyname` VARCHAR(255) CHARACTER SET ascii NOT NULL,
	PRIMARY KEY (`propertyid`),
	UNIQUE (`propertyname`)
) DEFAULT CHARSET=utf8, ENGINE=InnoDB;

--
-- Image gallery folders.
--
CREATE TABLE `#__sigplus_folder` (
	`folderid` INT UNSIGNED NOT NULL AUTO_INCREMENT,
	-- relative path w.r.t. Joomla root, absolute path, or URL
	`folderurl` VARCHAR(767) CHARACTER SET ascii NOT NULL,
	-- last modified time for folder
	`foldertime` DATETIME,
	-- HTTP ETag
	`entitytag` VARCHAR(255) CHARACTER SET ascii,
	-- default title for images in folder
	`title` VARCHAR(64000),
	-- default summary text for images in folder
	`summary` VARCHAR(64000),
	PRIMARY KEY (`folderid`),
	UNIQUE (`folderurl`)
) DEFAULT CHARSET=utf8, ENGINE=InnoDB;

--
-- Ancestor-descendant relationships for image gallery folders.
--
CREATE TABLE `#__sigplus_hierarchy` (
	`ancestorid` INT UNSIGNED NOT NULL,
	`descendantid` INT UNSIGNED NOT NULL,
	`depthnum` SMALLINT UNSIGNED NOT NULL,
	PRIMARY KEY (`ancestorid`,`descendantid`),
	FOREIGN KEY (`ancestorid`) REFERENCES `#__sigplus_folder`(`folderid`) ON DELETE CASCADE,
	FOREIGN KEY (`descendantid`) REFERENCES `#__sigplus_folder`(`folderid`) ON DELETE CASCADE,
	INDEX (`depthnum`)
) DEFAULT CHARSET=utf8, ENGINE=InnoDB;

--
-- Folder views.
--
CREATE TABLE `#__sigplus_view` (
	`viewid` INT UNSIGNED NOT NULL AUTO_INCREMENT,
	-- unique value computed from preview width, height, cropping and watermarking settings
	`hash` BINARY(16) NOT NULL,
	-- folder identifier
	`folderid` INT UNSIGNED NOT NULL,
	-- preview width for images in gallery
	`preview_width` SMALLINT UNSIGNED NOT NULL,
	-- preview height for images in gallery
	`preview_height` SMALLINT UNSIGNED NOT NULL,
	-- cropping mode for images in gallery
	`preview_crop` BOOLEAN NOT NULL,
	-- HTTP ETag
	`entitytag` VARCHAR(255) CHARACTER SET ascii,
	PRIMARY KEY (`viewid`),
	UNIQUE (`hash`),
	FOREIGN KEY (`folderid`) REFERENCES `#__sigplus_folder`(`folderid`) ON DELETE CASCADE
) DEFAULT CHARSET=utf8, ENGINE=InnoDB;

--
-- Image data (excluding metadata).
--
CREATE TABLE `#__sigplus_image` (
	`imageid` INT UNSIGNED NOT NULL AUTO_INCREMENT,
	`folderid` INT UNSIGNED,
	`fileurl` VARCHAR(767) CHARACTER SET ascii NOT NULL,
	`filename` VARCHAR(255) NOT NULL,
	`filetime` DATETIME,
	`ordnum` SMALLINT UNSIGNED,
	`width` SMALLINT UNSIGNED NOT NULL,
	`height` SMALLINT UNSIGNED NOT NULL,
	`title` VARCHAR(64000),
	`summary` VARCHAR(64000),
	PRIMARY KEY (`imageid`),
	UNIQUE (`fileurl`),
	INDEX (`ordnum`),
	FOREIGN KEY (`folderid`) REFERENCES `#__sigplus_folder`(`folderid`) ON DELETE CASCADE
) DEFAULT CHARSET=utf8, ENGINE=InnoDB;

--
-- Image views that associate images with preview sizes.
--
CREATE TABLE `#__sigplus_imageview` (
	`imageid` INT UNSIGNED NOT NULL,
	`viewid` INT UNSIGNED NOT NULL,
	`thumb_fileurl` VARCHAR(767) CHARACTER SET ascii NOT NULL,
	`thumb_filetime` DATETIME,
	`thumb_width` SMALLINT UNSIGNED NOT NULL,
	`thumb_height` SMALLINT UNSIGNED NOT NULL,
	`preview_fileurl` VARCHAR(767) CHARACTER SET ascii NOT NULL,
	`preview_filetime` DATETIME,
	`preview_width` SMALLINT UNSIGNED NOT NULL,
	`preview_height` SMALLINT UNSIGNED NOT NULL,
	`watermark_fileurl` VARCHAR(767) CHARACTER SET ascii,
	`watermark_filetime` DATETIME,
	PRIMARY KEY (`imageid`,`viewid`),
	FOREIGN KEY (`imageid`) REFERENCES `#__sigplus_image`(`imageid`) ON DELETE CASCADE,
	FOREIGN KEY (`viewid`) REFERENCES `#__sigplus_view`(`viewid`) ON DELETE CASCADE
) DEFAULT CHARSET=utf8, ENGINE=InnoDB;

--
-- Image metadata.
--
CREATE TABLE `#__sigplus_data` (
	`imageid` INT UNSIGNED NOT NULL,
	`propertyid` INT UNSIGNED NOT NULL,
	`textvalue` VARCHAR(64000),
	PRIMARY KEY (`imageid`, `propertyid`),
	FOREIGN KEY (`imageid`) REFERENCES `#__sigplus_image`(`imageid`) ON DELETE CASCADE,
	FOREIGN KEY (`propertyid`) REFERENCES `#__sigplus_property`(`propertyid`) ON DELETE CASCADE
) DEFAULT CHARSET=utf8, ENGINE=InnoDB;