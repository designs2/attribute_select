<?php
/**
 * The MetaModels extension allows the creation of multiple collections of custom items,
 * each with its own unique set of selectable attributes, with attribute extendability.
 * The Front-End modules allow you to build powerful listing and filtering of the
 * data in each collection.
 *
 * PHP version 5
 * @package     MetaModels
 * @subpackage  AttributeSelect
 * @author      Christian Schiffler <c.schiffler@cyberspectrum.de>
 * @author      Andreas Isaak <info@andreas-isaak.de>
 * @author      Oliver Hoff <oliver@hofff.com>
 * @author      Stefan heimes <stefan_heimes@hotmail.com>
 * @copyright   The MetaModels team.
 * @license     LGPL.
 * @filesource
 */

$GLOBALS['TL_DCA']['tl_metamodel_dcasetting']['metasubselectpalettes']['attr_id']['select'] = array
(
    'presentation' => array(
        'tl_class',
        'includeBlankOption',
        'submitOnChange',
        'chosen',
        'select_as_radio'
    ),
    'functions'  => array(
        'mandatory'
    ),
    'overview' => array(
        'filterable',
        'searchable'
    )
);

$GLOBALS['TL_DCA']['tl_metamodel_dcasetting']['palettes']['__selector__'][] = 'select_as_radio';

$GLOBALS['TL_DCA']['tl_metamodel_dcasetting']['metasubselectpalettes']['select_as_radio'][2] = array(
    'presentation after select_as_radio' => array(
        'select_minLevel', 'select_maxLevel'
    )
);

$GLOBALS['TL_DCA']['tl_metamodel_dcasetting']['fields']['select_as_radio'] = array
(
    'label'                 => &$GLOBALS['TL_LANG']['tl_metamodel_dcasetting']['select_as_radio'],
    'exclude'               => true,
    'inputType'             => 'select',
    'options'               => array(0, 1, 2),
    'reference'             => &$GLOBALS['TL_LANG']['tl_metamodel_dcasetting']['select_as_radio_reference'],
    'eval'                  => array
    (
        'tl_class'          => 'clr'
    )
);

$GLOBALS['TL_DCA']['tl_metamodel_dcasetting']['fields']['select_minLevel'] = array
(
    'label'     => &$GLOBALS['TL_LANG']['tl_metamodel_dcasetting']['select_minLevel'],
    'exclude'   => true,
    'inputType' => 'text',
    'eval'      => array
    (
        'tl_class' => 'clr w50'
    )
);

$GLOBALS['TL_DCA']['tl_metamodel_dcasetting']['fields']['select_maxLevel'] = array
(
    'label'     => &$GLOBALS['TL_LANG']['tl_metamodel_dcasetting']['select_maxLevel'],
    'exclude'   => true,
    'inputType' => 'text',
    'eval'      => array
    (
        'tl_class' => 'w50'
    )
);
