<?php
/**
 * giua@school
 *
 * Copyright (c) 2017 Antonello Dessì
 *
 * @author    Antonello Dessì
 * @license   http://www.gnu.org/licenses/agpl.html AGPL
 * @copyright Antonello Dessì 2017
 */


namespace AppBundle\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;


/**
 * ScrutinioAssenzaType - form per la classe ScrutinioAssenza
 */
class ScrutinioAssenzaType extends AbstractType {

  /**
   * Crea il form
   *
   * @param FormBuilderInterface $builder Gestore per la creazione del form
   * @param array $options Lista di opzioni per il form
   */
  public function buildForm(FormBuilderInterface $builder, array $options) {
    // aggiunge campi al form
    $builder
      ->add('alunno', HiddenType::class)
      ->add('sesso', HiddenType::class)
      ->add('scrutinabile', ChoiceType::class, array(
        'choices' => ['label.no_scrutinabile_cessata_frequenza' => 'C', 'label.label.no_scrutinabile_assenze' => 'A',
          'label.scrutinabile_deroga' => 'D'],
        'expanded' => true,
        'multiple' => false,
        'label_attr' => ['class' => 'gs-text-normal'],
        'required' => true))
      ->add('motivazione', TextareaType::class, array(
        'attr' => ['rows' => 4],
        'trim' => true,
        'required' => false))
      ->add('testo', ChoiceType::class, array(
        'choices' => ['label.deroga_salute' => 'S', 'label.deroga_famiglia' => 'F', 'label.deroga_sport' => 'P',
          'label.deroga_religione' => 'R'],
        'placeholder' => 'label.inserisci_motivazione',
        'expanded' => false,
        'multiple' => false,
        'choice_attr' => function($val, $key, $index) {
            return ['class' => 'gs-no-placeholder'];
          },
        'attr' => ['class' => 'gs-placeholder'],
        'required' => false,
        'mapped' => false));
  }

  /**
   * Configura le opzioni usate nel form
   *
   * @param OptionsResolver $resolver Gestore delle opzioni
   */
  public function configureOptions(OptionsResolver $resolver) {
    $resolver->setDefaults(array('data_class' => ScrutinioAssenza::class));
  }

}

