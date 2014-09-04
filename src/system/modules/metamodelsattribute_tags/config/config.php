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
 * @copyright The MetaModels team.
 * @license   LGPL.
 * @filesource
 */

$GLOBALS['METAMODELS']['attributes']['tags']['class'] = 'MetaModels\Attribute\Tags\Tags';
$GLOBALS['METAMODELS']['attributes']['tags']['image'] = 'system/modules/metamodelsattribute_tags/html/tags.png';

$GLOBALS['TL_EVENTS'][\ContaoCommunityAlliance\Contao\EventDispatcher\Event\CreateEventDispatcherEvent::NAME][] =
	'MetaModels\DcGeneral\Events\Table\Attribute\Tags\Subscriber::registerEvents';
