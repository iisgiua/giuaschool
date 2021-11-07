<?php
/**
 * giua@school
 *
 * Copyright (c) 2017-2021 Antonello Dessì
 *
 * @author    Antonello Dessì
 * @license   http://www.gnu.org/licenses/agpl.html AGPL
 * @copyright Antonello Dessì 2017-2021
 */


namespace App\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\Extension\Core\Type\ButtonType;


/**
 * AlunnoGenitoreType - form per la classe Alunno
 */
class AlunnoGenitoreType extends AbstractType {

  /**
   * Crea il form
   *
   * @param FormBuilderInterface $builder Gestore per la creazione del form
   * @param array $options Lista di opzioni per il form
   */
  public function buildForm(FormBuilderInterface $builder, array $options) {
    // form di modifica
    $builder
      ->add('alunno', AlunnoType::class, array('label' => false,
        'data' => $options['data'][0],
        'row_attr' => ['class' => 'mb-0'],
        'mapped' => false))
      ->add('genitore1', GenitoreType::class, array('label' => false,
        'data' => $options['data'][1],
        'row_attr' => ['class' => 'mb-0'],
        'mapped' => false))
      ->add('genitore2', GenitoreType::class, array('label' => false,
        'data' => $options['data'][2],
        'row_attr' => ['class' => 'mb-0'],
        'mapped' => false))
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
    $resolver->setDefined('data');
    $resolver->setDefaults(array(
      'returnUrl' => null,
      'data' => null));
  }

}
