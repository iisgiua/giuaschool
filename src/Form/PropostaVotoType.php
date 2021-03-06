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
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use App\Entity\PropostaVoto;


/**
 * PropostaVotoType - form per la classe PropostaVoto
 */
class PropostaVotoType extends AbstractType {

  /**
   * Crea il form
   *
   * @param FormBuilderInterface $builder Gestore per la creazione del form
   * @param array $options Lista di opzioni per il form
   */
  public function buildForm(FormBuilderInterface $builder, array $options) {
    // aggiunge campi al form
    $builder
      ->add('alunno', HiddenType::class, array('property_path' => 'alunno.id'))
      ->add('unico', HiddenType::class)
      ->add('recupero', ChoiceType::class, array('label' => false,
        'choices' => ['label.recupero_A' => 'A', 'label.recupero_P' => 'P',
          'label.recupero_S' => 'S', 'label.recupero_C' => 'C', 'label.recupero_I' => 'I',
          'label.recupero_R' => 'R', 'label.recupero_N' => 'N'],
        'placeholder' => 'label.scegli_recupero',
        'expanded' => false,
        'multiple' => false,
        'choice_attr' => function($val, $key, $index) {
            return ['class' => 'gs-no-placeholder'];
          },
        'attr' => ['class' => 'gs-placeholder'],
        'required' => false))
      ->add('debito', TextareaType::class, array('label' => false,
        'trim' => true,
        'attr' => array('rows' => '3'),
        'required' => false));
      //-- // aggiunta COVID
      //-- ->add('strategie', TextareaType::class, array('label' => false,
        //-- 'trim' => true,
        //-- 'attr' => array('rows' => '3'),
        //-- 'required' => false,
        //-- 'property_path' => 'dati[strategie]'));
  }

  /**
   * Configura le opzioni usate nel form
   *
   * @param OptionsResolver $resolver Gestore delle opzioni
   */
  public function configureOptions(OptionsResolver $resolver) {
    $resolver->setDefaults(array('data_class' => PropostaVoto::class));
  }

}
