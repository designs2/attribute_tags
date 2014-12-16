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
 * @author      Stefan heimes <stefan_heimes@hotmail.com>
 * @author      Andreas Isaak <info@andreas-isaak.de>
 * @copyright   The MetaModels team.
 * @license     LGPL.
 * @filesource
 */

use MetaModels\Attribute\Events\CreateAttributeFactoryEvent;
use MetaModels\Attribute\Tags\AttributeTypeFactory;
use MetaModels\DcGeneral\Events\MetaModels\Tags\BackendSubscriber;
use MetaModels\DcGeneral\Events\Table\Attribute\Tags\Subscriber;
use MetaModels\Events\MetaModelsBootEvent;
use MetaModels\MetaModelsEvents;

return array
(
    MetaModelsEvents::SUBSYSTEM_BOOT_BACKEND => function (MetaModelsBootEvent $event) {
        new Subscriber($event->getServiceContainer());
        new BackendSubscriber($event->getServiceContainer());
    },
    MetaModelsEvents::ATTRIBUTE_FACTORY_CREATE => function (CreateAttributeFactoryEvent $event) {
        $factory = $event->getFactory();
        $factory->addTypeFactory(new AttributeTypeFactory());
    }
);
