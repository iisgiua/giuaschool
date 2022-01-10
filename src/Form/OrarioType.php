<?php
/**
 * giua@school
 *
 * Copyright (c) 2017-2022 Antonello Dessì
 *
 * @author    Antonello Dessì
 * @license   http://www.gnu.org/licenses/agpl.html AGPL
 * @copyright Antonello Dessì 2017-2022
 */


namespace App\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\Extension\Core\Type\ButtonType;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Doctrine\ORM\EntityRepository;
use App\Entity\Orario;


/**
 * OrarioType - form per la classe Orario
 */
class OrarioType extends AbstractType {

  /**
   * Crea il form
   *
   * @param FormBuilderInterface $builder Gestore per la creazione del form
   * @param array $options Lista di opzioni per il form
   */
  public function buildForm(FormBuilderInterface $builder, array $options) {
    // aggiunge campi al form
    $builder
      ->add('nome', TextType::class, array('label' => 'label.nome_orario',
        'required' => true))
      ->add('inizio', DateType::class, array('label' => 'label.data_inizio',
        'widget' => 'single_text',
        'html5' => false,
        'format' => 'dd/MM/yyyy',
        'attr' => ['widget' => 'gs-row-start'],
        'required' => true))
      ->add('fine', DateType::class, array('label' => 'label.data_fine',
        'widget' => 'single_text',
        'html5' => false,
        'format' => 'dd/MM/yyyy',
        'attr' => ['widget' => 'gs-row-end'],
        'required' => true))
      ->add('sede', EntityType::class, array('label' => 'label.sede',
        'class' => 'App:Sede',
        'choice_label' => function ($obj) {
            return $obj->getNomeBreve();
          },
        'query_builder' => function (EntityRepository $er) {
            return $er->createQueryBuilder('s')
              ->orderBy('s.ordinamento', 'ASC');
          },
        'required' => true))
      ->add('submit', SubmitType::class, array('label' => 'label.submit',
        'attr' => ['widget' => 'gs-button-start']))
      ->add('cancel', ButtonType::class, array('label' => 'label.cancel',
        'attr' => ['widget' => 'gs-button-end', 'onclick' => "location.href='".$options['returnUrl']."'"]));
  }

  /**
   * Configura le opzioni usate nel form
   *
   * @param OptionsResolver $resolver Gestore delle opzioni
   */
  public function configureOptions(OptionsResolver $resolver) {
    $resolver->setDefined('returnUrl');
    $resolver->setDefaults(array(
      'returnUrl' => null,
      'data_class' => Orario::class));
  }

}
