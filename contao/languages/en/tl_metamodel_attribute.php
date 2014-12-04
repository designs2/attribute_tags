<?php
/**
 * The MetaModels extension allows the creation of multiple collections of custom items,
 * each with its own unique set of selectable attributes, with attribute extendability.
 * The Front-End modules allow you to build powerful listing and filtering of the
 * data in each collection.
 *
 * PHP version 5
 *
 * @package    AttributeTags
 * @author     Christian Schiffler <c.schiffler@cyberspectrum.de>
 * @author     Christian de la Haye <service@delahaye.de>
 * @copyright  The MetaModels team.
 * @license    LGPL.
 * @filesource
 */

// Legends.
$GLOBALS['TL_LANG']['tl_metamodel_attribute']['display_legend'] = 'Display settings';

// Fields.
$GLOBALS['TL_LANG']['tl_metamodel_attribute']['typeOptions']['tags'] = 'Tags';
$GLOBALS['TL_LANG']['tl_metamodel_attribute']['tag_table'][0]        = 'Database table';
$GLOBALS['TL_LANG']['tl_metamodel_attribute']['tag_table'][1]        = 'Please select the database table.';
$GLOBALS['TL_LANG']['tl_metamodel_attribute']['tag_column'][0]       = 'Table column';
$GLOBALS['TL_LANG']['tl_metamodel_attribute']['tag_column'][1]       = 'Please select the column.';
$GLOBALS['TL_LANG']['tl_metamodel_attribute']['tag_id'][0]           = 'Tag ID';
$GLOBALS['TL_LANG']['tl_metamodel_attribute']['tag_id'][1]           = 'Please select a entry for the tag id.';
$GLOBALS['TL_LANG']['tl_metamodel_attribute']['tag_alias'][0]        = 'Tag alias';
$GLOBALS['TL_LANG']['tl_metamodel_attribute']['tag_alias'][1]        = 'Please select a entry for the tag alias.';
$GLOBALS['TL_LANG']['tl_metamodel_attribute']['tag_sorting'][0]      = 'Tag sorting';
$GLOBALS['TL_LANG']['tl_metamodel_attribute']['tag_sorting'][1]      = 'Please select a entry for the tag sorting.';
$GLOBALS['TL_LANG']['tl_metamodel_attribute']['tag_where'][0]        = 'SQL';
$GLOBALS['TL_LANG']['tl_metamodel_attribute']['tag_where'][1]        =
    'The list of options can be limited by using SQL.';
$GLOBALS['TL_LANG']['tl_metamodel_attribute']['tag_filter'][0]       = 'Filter';
$GLOBALS['TL_LANG']['tl_metamodel_attribute']['tag_filter'][1]       = 'Here you can choose the filter to use.';
$GLOBALS['TL_LANG']['tl_metamodel_attribute']['tag_filterparams'][0] = 'Filter parameters';
$GLOBALS['TL_LANG']['tl_metamodel_attribute']['tag_filterparams'][1] = 'Here you can choose a default value for the filter.';

/**
 * Misc.
 */
$GLOBALS['TL_LANG']['tl_metamodel_attribute']['sql_error']             = 'The SQL query causes an error.';

$GLOBALS['TL_LANG']['tl_metamodel_attribute']['tag_table_type']['translated']   = 'Translated MetaModels';
$GLOBALS['TL_LANG']['tl_metamodel_attribute']['tag_table_type']['untranslated'] = 'Untranslated MetaModels';
$GLOBALS['TL_LANG']['tl_metamodel_attribute']['tag_table_type']['sql-table']    = 'SQL Table';

$GLOBALS['TL_LANG']['tl_metamodel_attribute']['tag_column_type']['attribute'] = 'MetaModel attributes';
$GLOBALS['TL_LANG']['tl_metamodel_attribute']['tag_column_type']['sql']       = 'SQL table column';
