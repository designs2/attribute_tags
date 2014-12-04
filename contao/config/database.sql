-- **********************************************************
-- *                                                        *
-- * IMPORTANT NOTE                                         *
-- *                                                        *
-- * Do not import this file manually but use the Contao    *
-- * install tool to create and maintain database tables!   *
-- *                                                        *
-- **********************************************************

--
-- Table `tl_metamodel_attribute`
--

CREATE TABLE `tl_metamodel_attribute` (
  `tag_table` varchar(255) NOT NULL default '',
  `tag_column` varchar(255) NOT NULL default '',
  `tag_id` varchar(255) NOT NULL default '',
  `tag_alias` varchar(255) NOT NULL default '',
  `tag_sorting` varchar(255) NOT NULL default '',
  `tag_where` text NULL,
  `tag_filter` int(11) unsigned NOT NULL default '0',
  `tag_filterparams` text NULL
) ENGINE=MyISAM DEFAULT CHARSET=utf8;

--
-- Table `tl_metamodel_tag_relation`
--

CREATE TABLE `tl_metamodel_tag_relation` (
  `id` int(11) unsigned NOT NULL auto_increment,
  `att_id` int(11) unsigned NOT NULL default '0',
  `item_id` int(11) unsigned NOT NULL default '0',
  `value_sorting` int(11) unsigned NOT NULL default '0',
  `value_id` int(11) unsigned NOT NULL default '0',
  PRIMARY KEY  (`id`),
  KEY `att_id` (`att_id`),
  KEY `item_id` (`item_id`),
  KEY `value_id` (`value_id`),
  KEY `tagid` (`item_id`, `att_id`, `value_id`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8;

--
-- Table `tl_metamodel_dcasetting`
--

CREATE TABLE `tl_metamodel_dcasetting` (
  `tag_as_wizard` varchar(1) NOT NULL default '0'
) ENGINE=MyISAM DEFAULT CHARSET=utf8;
