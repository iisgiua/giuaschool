<?php
/*
 * SPDX-FileCopyrightText: 2017 I.I.S. Michele Giua - Cagliari - Assemini
 *
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */


namespace App\Form;

use App\Entity\Presenza;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\CallbackTransformer;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\TimeType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;


/**
 * PresenzaType - form per la classe Presenza
 *
 * @author Antonello Dessì
 */
class PresenzaType extends AbstractType {

  //==================== ATTRIBUTI DELLA CLASSE  ====================

  /**
   * @var EntityManagerInterface $em Gestore delle entità
   */
  private $em;


  //==================== METODI DELLA CLASSE ====================

  /**
   * Costruttore
   *
   * @param EntityManagerInterface $em Gestore delle entità
   */
  public function __construct(EntityManagerInterface $em) {
    $this->em = $em;
  }

  /**
   * Crea il form
   *
   * @param FormBuilderInterface $builder Gestore per la creazione del form
   * @param array $options Lista di opzioni per il form
   */
  public function buildForm(FormBuilderInterface $builder, array $options) {
    if ($options['form_mode'] == 'add') {
      // form aggiungi
      $builder
        ->add('alunno', HiddenType::class)
        ->add('alunni', ChoiceType::class, ['label' => 'label.alunni',
          'choices' => $options['values'][0],
          'choice_translation_domain' => false,
          'expanded' => true,
          'multiple' => true,
          'choice_value' => 'id',
          'label_attr' => ['class' => 'checkbox-split-vertical'],
          'required' => true,
          'mapped' => false])
        ->add('data', DateType::class, array('label' => 'label.data_inizio',
          'widget' => 'single_text',
          'html5' => false,
          'attr' => ['widget' => 'gs-picker'],
          'format' => 'dd/MM/yyyy',
          'required' => true))
        ->add('dataFine', DateType::class, array('label' => 'label.data_fine',
          'widget' => 'single_text',
          'html5' => false,
          'attr' => ['widget' => 'gs-picker'],
          'format' => 'dd/MM/yyyy',
          'required' => true,
          'mapped' => false))
        ->add('settimana', ChoiceType::class, array('label' => false,
          'choices' => ['label.lunedi' => '1', 'label.martedi' => '2', 'label.mercoledi' => '3',
            'label.giovedi' => '4', 'label.venerdi' => '5', 'label.sabato' => '6'],
          'expanded' => true,
          'multiple' => true,
          'label_attr' => ['class' => 'gs-checkbox-inline gs-mr-5 gs-pr-5'],
          'required' => true,
          'mapped' => false))
        ->add('oraTipo', ChoiceType::class, array('label' => false,
          'choices' => array('label.presenza_ora_tipo_G' => 'G', 'label.presenza_ora_tipo_F' => 'F',
            'label.presenza_ora_tipo_I' => 'I'),
          'expanded' => false,
          'multiple' => false,
          'attr' => ['class' => 'gs-placeholder'],
          'required' => true,
          'mapped' => false))
        ->add('oraInizio', TimeType::class, array('label' => 'label.ora_inizio',
          'widget' => 'single_text',
          'html5' => false,
          'attr' => ['widget' => 'gs-picker'],
          'required' => false))
        ->add('oraFine', TimeType::class, array('label' => 'label.ora_fine',
          'widget' => 'single_text',
          'html5' => false,
          'attr' => ['widget' => 'gs-picker'],
          'required' => false))
        ->add('tipo', ChoiceType::class, array('label' => 'label.tipo',
          'choices' => array('label.presenza_tipo_P' => 'P', 'label.presenza_tipo_M' => 'M',
            'label.presenza_tipo_S' => 'S', 'label.presenza_tipo_E' => 'E'),
          'expanded' => false,
          'multiple' => false,
          'attr' => ['style' => 'width: auto'],
          'required' => true))
        ->add('descrizione', TextType::class, array('label' => 'label.descrizione',
          'required' => true));
      // aggiunge data transform
      $builder->get('alunno')->addModelTransformer(new CallbackTransformer(
        function ($alunno) {
          return 0;
        },
        function ($id) {
          return $this->em->getRepository('App\Entity\Alunno')->find($id);
        }));
    } elseif ($options['form_mode'] == 'edit') {
      // form modifica
      $builder
        ->add('alunno', ChoiceType::class, ['label' => 'label.alunno',
          'choices' => $options['values'][0],
          'choice_value' => 'id',
          'choice_translation_domain' => false,
          'required' => true])
        ->add('data', DateType::class, array('label' => 'label.data',
          'widget' => 'single_text',
          'html5' => false,
          'attr' => ['widget' => 'gs-picker'],
          'format' => 'dd/MM/yyyy',
          'required' => true))
        ->add('oraTipo', ChoiceType::class, array('label' => false,
          'choices' => array('label.presenza_ora_tipo_G' => 'G', 'label.presenza_ora_tipo_F' => 'F',
            'label.presenza_ora_tipo_I' => 'I'),
          'expanded' => false,
          'multiple' => false,
          'attr' => ['class' => 'gs-placeholder'],
          'required' => true,
          'mapped' => false))
        ->add('oraInizio', TimeType::class, array('label' => 'label.ora_inizio',
          'widget' => 'single_text',
          'html5' => false,
          'attr' => ['widget' => 'gs-picker'],
          'required' => false))
        ->add('oraFine', TimeType::class, array('label' => 'label.ora_fine',
          'widget' => 'single_text',
          'html5' => false,
          'attr' => ['widget' => 'gs-picker'],
          'required' => false))
        ->add('tipo', ChoiceType::class, array('label' => 'label.tipo',
          'choices' => array('label.presenza_tipo_P' => 'P', 'label.presenza_tipo_M' => 'M',
            'label.presenza_tipo_S' => 'S', 'label.presenza_tipo_E' => 'E'),
          'expanded' => false,
          'multiple' => false,
          'attr' => ['style' => 'width: auto'],
          'required' => true))
        ->add('descrizione', TextType::class, array('label' => 'label.descrizione',
          'required' => true));
    } elseif ($options['form_mode'] == 'registro') {
      // form modifica da registro assenze
      $builder
        ->add('oraTipo', ChoiceType::class, array('label' => false,
          'choices' => array('label.presenza_ora_tipo_G' => 'G', 'label.presenza_ora_tipo_F' => 'F',
            'label.presenza_ora_tipo_I' => 'I'),
          'expanded' => false,
          'multiple' => false,
          'attr' => ['class' => 'gs-placeholder'],
          'required' => true,
          'mapped' => false))
        ->add('oraInizio', TimeType::class, array('label' => 'label.ora_inizio',
          'widget' => 'single_text',
          'html5' => false,
          'attr' => ['widget' => 'gs-picker'],
          'required' => false))
        ->add('oraFine', TimeType::class, array('label' => 'label.ora_fine',
          'widget' => 'single_text',
          'html5' => false,
          'attr' => ['widget' => 'gs-picker'],
          'required' => false))
        ->add('tipo', ChoiceType::class, array('label' => 'label.tipo',
          'choices' => array('label.presenza_tipo_P' => 'P', 'label.presenza_tipo_M' => 'M',
            'label.presenza_tipo_S' => 'S', 'label.presenza_tipo_E' => 'E'),
          'expanded' => false,
          'multiple' => false,
          'attr' => ['style' => 'width: auto'],
          'required' => true))
        ->add('descrizione', TextType::class, array('label' => 'label.descrizione',
          'required' => true));
    }
  }

  /**
   * Configura le opzioni usate nel form
   *
   * @param OptionsResolver $resolver Gestore delle opzioni
   */
  public function configureOptions(OptionsResolver $resolver) {
    $resolver->setDefined('form_mode');
    $resolver->setDefined('return_url');
    $resolver->setDefined('values');
    $resolver->setDefaults(array(
      'form_mode' => 'edit',
      'return_url' => null,
      'values' => [],
      'allow_extra_fields' => true,
      'data_class' => Presenza::class));
  }

}
