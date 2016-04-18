<?php

use Doctrine\ORM\EntityManager;
use Gedmo\Translatable\Entity\Repository\TranslationRepository;
use Stof\DoctrineExtensionsBundle\Translatable\TranslatableFormListener;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\Form\Extension\Core\Type\FormType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilder;
use Symfony\Component\Form\Tests\AbstractFormTest;

class TranslatableFormListenerTest extends  \PHPUnit_Framework_TestCase
{
    public function testFormSubmit()
    {
        $formType = TextType::class;
        $formOptions = [];

        $data = new stdClass();
        $data->foo = 'foo';

        $translation = [
            'fr'=>['foo'=>'toto']
        ];
        $translationRepository = $this->getMockBuilder(TranslationRepository::class)
            ->setMethods(array('findTranslations', 'translate'))
            ->disableOriginalConstructor()
            ->getMock();

        $translationRepository
            ->expects($this->once())
            ->method('findTranslations')
            ->willReturn($translation);

        $translationRepository
            ->expects($this->once())
            ->method('translate')
            ->with($this->equalTo($data), 'foo', 'fr', 'toto');

        $em = $this->getMockBuilder(EntityManager::class)
            ->setMethods(array('getRepository'))
            ->disableOriginalConstructor()
            ->getMock();

        $em->expects($this->once())->method('getRepository')->willReturn($translationRepository);

        $factoryBuilder = new \Symfony\Component\Form\FormFactoryBuilder();
        $factory = $factoryBuilder->getFormFactory();
        $formBuilder = new FormBuilder('test', 'stdClass', new EventDispatcher(), $factory, array(
            TranslatableFormListener::LOCALES_SETTING => array('fr'),
            TranslatableFormListener::FIELDS_SETTING => array('foo'),
        ));

        $formBuilder->setCompound(true)
            ->setDataMapper($this->getMock('Symfony\Component\Form\DataMapperInterface'))
            ->setAutoInitialize(true);

        $formBuilder->add('foo', $formType, $formOptions);
        $formBuilder->addEventSubscriber(new TranslatableFormListener($em));

        $form = $formBuilder->getForm();
        $form->setData($data);

        $this->assertNotNull($form->get('foo_fr'));
        $this->assertSame($form->get('foo_fr')->getData(), $translation['fr']['foo']);

        $form->handleRequest();
        $form->submit($data);
    }
}