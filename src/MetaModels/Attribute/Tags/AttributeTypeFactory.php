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
 * @copyright   The MetaModels team.
 * @license     LGPL.
 * @filesource
 */

namespace MetaModels\Attribute\Tags;

use MetaModels\Attribute\Events\CreateAttributeFactoryEvent;
use MetaModels\Attribute\IAttributeTypeFactory;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Attribute type factory for select attributes.
 */
class AttributeTypeFactory implements EventSubscriberInterface, IAttributeTypeFactory
{
    /**
     * {@inheritDoc}
     */
    public static function getSubscribedEvents()
    {
        return array(
            CreateAttributeFactoryEvent::NAME => 'registerLegacyAttributeFactoryEvents'
        );
    }

    /**
     * Register all legacy factories and all types defined via the legacy array as a factory.
     *
     * @param CreateAttributeFactoryEvent $event The event.
     *
     * @return void
     */
    public static function registerLegacyAttributeFactoryEvents(CreateAttributeFactoryEvent $event)
    {
        $factory = $event->getFactory();
        $factory->addTypeFactory(new static());
    }

    /**
     * {@inheritdoc}
     */
    public function getTypeName()
    {
        return 'tags';
    }

    /**
     * {@inheritdoc}
     */
    public function getTypeIcon()
    {
        return 'system/modules/metamodelsattribute_tags/html/tags.png';
    }

    /**
     * {@inheritdoc}
     */
    public function createInstance($information, $metaModel)
    {
        if (substr($information['tag_table'], 0, 3) === 'mm_') {
            return new MetaModelTags($metaModel, $information);
        }

        return new Tags($metaModel, $information);
    }

    /**
     * Check if the type is translated.
     *
     * @return bool
     */
    public function isTranslatedType()
    {
        return false;
    }

    /**
     * Check if the type is of simple nature.
     *
     * @return bool
     */
    public function isSimpleType()
    {
        return false;
    }

    /**
     * Check if the type is of complex nature.
     *
     * @return bool
     */
    public function isComplexType()
    {
        return true;
    }
}