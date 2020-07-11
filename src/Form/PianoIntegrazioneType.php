<?php
/**
 * giua@school
 *
 * Copyright (c) 2017-2020 Antonello Dessì
 *
 * @author    Antonello Dessì
 * @license   http://www.gnu.org/licenses/agpl.html AGPL
 * @copyright Antonello Dessì 2017-2020
 */


namespace App\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\ButtonType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use App\Entity\DocumentoInterno;


/**
 * PropostaVotoType - form per la classe PropostaVoto
 */
class PianoIntegrazioneType extends AbstractType {

  /**
   * Crea il form
   *
   * @param FormBuilderInterface $builder Gestore per la creazione del form
   * @param array $options Lista di opzioni per il form
   */
  public function buildForm(FormBuilderInterface $builder, array $options) {
    // aggiunge campi al form
    $builder
      ->add('necessario', ChoiceType::class, array('label' => 'label.PIA_necessario',
        'choices' => ['label.si' => true, 'label.no' => false],
        'expanded' => true,
        'multiple' => false,
        'label_attr' => ['class' => 'radio-inline'],
        'required' => true,
        'property_path' => 'dati[necessario]'))
      ->add('obiettivi', TextareaType::class, array('label' => 'label.obiettivi_apprendimento',
        'trim' => true,
        'attr' => array('rows' => '5'),
        'required' => false,
        'property_path' => 'dati[obiettivi]'))
      ->add('strategie', TextareaType::class, array('label' => 'label.strategie_apprendimento',
        'trim' => true,
        'attr' => array('rows' => '5'),
        'required' => false,
        'property_path' => 'dati[strategie]'));
  }

  /**
   * Configura le opzioni usate nel form
   *
   * @param OptionsResolver $resolver Gestore delle opzioni
   */
  public function configureOptions(OptionsResolver $resolver) {
    $resolver->setDefaults(array('data_class' => DocumentoInterno::class));
  }

}
