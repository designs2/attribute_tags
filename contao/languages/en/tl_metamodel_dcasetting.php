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
 * @author     Stefan Heimes <cms@men-at-work.de>
 * @copyright  The MetaModels team.
 * @license    LGPL.
 * @filesource
 */

// Fields.
$GLOBALS['TL_LANG']['tl_metamodel_dcasetting']['tag_as_wizard'][0] = 'Display as checkbox wizard';
$GLOBALS['TL_LANG']['tl_metamodel_dcasetting']['tag_as_wizard'][1] =
    'Select this options to change the display type from "checkbox" to a "checkboxwizard".';

$GLOBALS['TL_LANG']['tl_metamodel_dcasetting']['tag_minLevel'][0] = 'Minimum level in tree picker';
$GLOBALS['TL_LANG']['tl_metamodel_dcasetting']['tag_minLevel'][1] =
    'If you pass a value >0 here, no item below this level will be selectable.';
$GLOBALS['TL_LANG']['tl_metamodel_dcasetting']['tag_maxLevel'][0] = 'Maximum level in tree picker';
$GLOBALS['TL_LANG']['tl_metamodel_dcasetting']['tag_maxLevel'][1] =
    'If you pass a value >0 here, no item above this level will be selectable.';

// Reference.
$GLOBALS['TL_LANG']['tl_metamodel_dcasetting']['tag_as_wizard_reference'][0] = 'Display as checkbox menu';
$GLOBALS['TL_LANG']['tl_metamodel_dcasetting']['tag_as_wizard_reference'][1] = 'Display as checkbox wizard';
$GLOBALS['TL_LANG']['tl_metamodel_dcasetting']['tag_as_wizard_reference'][2] = 'Display as picker popup';
$GLOBALS['TL_LANG']['tl_metamodel_dcasetting']['tag_as_wizard_reference'][3] = 'Display as tag list';
