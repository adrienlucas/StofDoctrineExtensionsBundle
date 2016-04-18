<?php

namespace Stof\DoctrineExtensionsBundle\Translatable;


use Doctrine\ORM\EntityManager;
use Gedmo\Translatable\Entity\Repository\TranslationRepository;
use Gedmo\Translatable\Entity\Translation;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormEvents;

class TranslatableFormListener implements EventSubscriberInterface
{
    const LOCALES_SETTING = 'additional_locales';
    const FIELDS_SETTING = 'locatable_fields';

    /**
     * @var TranslationRepository
     */
    private $translationRepository;

    public static function getSubscribedEvents()
    {
        return array(
            FormEvents::PRE_SET_DATA => 'onPreSetData',
            FormEvents::POST_SET_DATA => 'onPostSetData',
            FormEvents::POST_SUBMIT   => 'onPostSubmit',
        );
    }

    /**
     * @param EntityManager $em
     */
    public function __construct(EntityManager $em)
    {
        $this->translationRepository = $em->getRepository(Translation::class);
    }

    /**
     * Add localised form children
     *
     * @param FormEvent $event
     */
    public function onPreSetData(FormEvent $event)
    {
        $form = $event->getForm();
        $options = $form->getConfig()->getOptions();

        foreach($options[self::LOCALES_SETTING] as $local) {
            foreach($options[self::FIELDS_SETTING] as $field) {
                $form->add(
                    $field.'_'.$local,
                    get_class($form->get($field)->getConfig()->getType()->getInnerType()),
                    array_merge($form->get($field)->getConfig()->getOptions(), array('mapped'=>false))
                );
            }
        }
    }

    /**
     * Translate form data
     *
     * @param FormEvent $event
     */
    public function onPostSetData(FormEvent $event)
    {
        $entity = $event->getData();
        if($entity === null) {
            return;
        }

        $form = $event->getForm();
        $options = $form->getConfig()->getOptions();

        foreach($this->translationRepository->findTranslations($entity) as $local => $fields) {
            foreach($options[self::FIELDS_SETTING] as $field) {
                if(isset($fields[$field])) {
                    $form->get($field.'_'.$local)->setData($fields[$field]);
                }
            }
        }

    }

    /**
     * Record translation
     *
     * @param FormEvent $event
     */
    public function onPostSubmit(FormEvent $event)
    {
        $form = $event->getForm();
        $options = $form->getConfig()->getOptions();

        foreach($options[self::LOCALES_SETTING] as $local) {
            foreach($options[self::FIELDS_SETTING] as $field) {
                $this->translationRepository->translate(
                    $event->getData(),
                    $field, $local,
                    $form->get($field.'_'.$local)->getData()
                );
            }
        }
    }
}