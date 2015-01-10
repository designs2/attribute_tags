<?php
/**
 * The MetaModels extension allows the creation of multiple collections of custom items,
 * each with its own unique set of selectable attributes, with attribute extendability.
 * The Front-End modules allow you to build powerful listing and filtering of the
 * data in each collection.
 *
 * PHP version 5
 *
 * @package   AttributeTags
 * @author    Christian Schiffler <c.schiffler@cyberspectrum.de>
 * @author    Stefan Heimes <stefan_heimes@hotmail.com>
 * @author    Andreas Isaak <info@andreas-isaak.de>
 * @author    Christopher Boelter <christopher@boelter.eu>
 * @copyright The MetaModels team.
 * @license   LGPL.
 * @filesource
 */

$GLOBALS['TL_DCA']['tl_metamodel_dcasetting']['metasubselectpalettes']['attr_id']['tags'] = array
(
    'presentation' => array
    (
        'tl_class',
        'submitOnChange',
        'tag_as_wizard'
    ),
    'functions'    => array
    (
        'mandatory'
    ),
    'overview'     => array
    (
        'filterable',
        'searchable',
    )
);

$GLOBALS['TL_DCA']['tl_metamodel_dcasetting']['fields']['tag_as_wizard'] = array
(
    'label'     => &$GLOBALS['TL_LANG']['tl_metamodel_dcasetting']['tag_as_wizard'],
    'exclude'   => true,
    'inputType' => 'select',
    'options'   => array(0, 1, 2),
    'reference' => &$GLOBALS['TL_LANG']['tl_metamodel_dcasetting']['tag_as_wizard_reference'],
    'eval'      => array
    (
        'tl_class' => 'clr'
    )
);
