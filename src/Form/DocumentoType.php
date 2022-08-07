<?php
/*
 * SPDX-FileCopyrightText: 2017 I.I.S. Michele Giua - Cagliari - Assemini
 *
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */


namespace App\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\ButtonType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Doctrine\ORM\EntityRepository;
use App\Entity\Documento;


/**
 * DocumentoType - form per i documenti
 *
 * @author Antonello Dessì
 */
class DocumentoType extends AbstractType {

  /**
   * Crea il form
   *
   * @param FormBuilderInterface $builder Gestore per la creazione del form
   * @param array $options Lista di opzioni per il form
   */
  public function buildForm(FormBuilderInterface $builder, array $options) {
    // funzioni per l'elenco delle classi
    $fnSede = function(EntityRepository $er) {
      return $er->createQueryBuilder('c')
        ->orderBy('c.anno,c.sezione', 'ASC'); };
    if (in_array($options['formMode'], ['B', 'H', 'D', 'docenti', 'alunni']) &&
        !empty($options['values'][0])) {
      // filtro su sede
      $fnSede = function(EntityRepository $er) use ($options) {
        return $er->createQueryBuilder('c')
          ->where('c.sede=:sede')
          ->setParameter('sede', $options['values'][0])
          ->orderBy('c.anno,c.sezione', 'ASC'); };
    }
    if ($options['formMode'] == 'docenti') {
      // form filtro documenti docenti
      $builder
        ->add('filtro', ChoiceType::class, array('label' => 'label.filtro_documenti',
          'data' => $options['values'][1],
          'choices' => ['label.documenti_presenti' => 'D', 'label.documenti_mancanti' => 'M',
            'label.documenti_tutti' => 'T'],
          'label_attr' => ['class' => 'sr-only'],
          'choice_attr' => function($val) { return ['class' => 'gs-no-placeholder']; },
          'attr' => ['class' => 'gs-placeholder'],
          'required' => true))
        ->add('tipo', ChoiceType::class, array('label' => 'label.tipo_documenti',
          'data' => $options['values'][2],
          'choices' => ['label.piani' => 'L', 'label.programmi' => 'P', 'label.relazioni' => 'R',
            'label.maggio' => 'M'],
          'label_attr' => ['class' => 'sr-only'],
          'choice_attr' => function($val) { return ['class' => 'gs-no-placeholder']; },
          'attr' => ['class' => 'gs-placeholder'],
          'required' => true))
        ->add('classe', EntityType::class, array('label' => 'label.classe',
          'data' => $options['values'][3],
          'class' => 'App\Entity\Classe',
          'choice_label' => function($obj) { return $obj->getAnno().'ª '.$obj->getSezione(); },
          'placeholder' => 'label.tutte_classi',
          'query_builder' => $fnSede,
          'group_by' => function($obj) { return $obj->getSede()->getCitta(); },
          'label_attr' => ['class' => 'sr-only'],
          'choice_attr' => function() { return ['class' => 'gs-no-placeholder']; },
          'attr' => ['class' => 'gs-placeholder'],
          'required' => false))
        ->add('submit', SubmitType::class, array('label' => 'label.filtra',
          'attr' => ['class' => 'btn-primary']));
      return;
    }
    if ($options['formMode'] == 'alunni') {
      // form filtro documenti alunni
      $builder
        ->add('tipo', ChoiceType::class, array('label' => 'label.tipo_documenti',
          'data' => $options['values'][1],
          'choices' => ['label.documenti_bes_B' => 'B', 'label.documenti_bes_H' => 'H',
            'label.documenti_bes_D' => 'D'],
          'placeholder' => 'label.tutti_tipi_documento',
          'label_attr' => ['class' => 'sr-only'],
          'choice_attr' => function($val) { return ['class' => 'gs-no-placeholder']; },
          'attr' => ['class' => 'gs-placeholder'],
          'required' => false))
        ->add('classe', EntityType::class, array('label' => 'label.classe',
          'data' => $options['values'][2],
          'class' => 'App\Entity\Classe',
          'choice_label' => function($obj) { return $obj->getAnno().'ª '.$obj->getSezione(); },
          'placeholder' => 'label.tutte_classi',
          'query_builder' => $fnSede,
          'group_by' => function($obj) { return $obj->getSede()->getCitta(); },
          'label_attr' => ['class' => 'sr-only'],
          'choice_attr' => function() { return ['class' => 'gs-no-placeholder']; },
          'attr' => ['class' => 'gs-placeholder'],
          'required' => false))
        ->add('submit', SubmitType::class, array('label' => 'label.filtra',
          'attr' => ['class' => 'btn-primary']));
      return;
    }
    if ($options['formMode'] == 'bacheca') {
      // form filtro documenti docenti
      $builder
        ->add('tipo', ChoiceType::class, array('label' => 'label.tipo_documenti',
          'data' => $options['values'][0],
          'choices' => $options['values'][1],
          'placeholder' => 'label.documenti_tutti',
          'label_attr' => ['class' => 'sr-only'],
          'choice_attr' => function($val) { return ['class' => 'gs-no-placeholder']; },
          'attr' => ['class' => 'gs-placeholder'],
          'required' => false))
        ->add('titolo', TextType::class, array('label' => 'label.titolo_documento',
          'data' => $options['values'][2],
          'attr' => ['placeholder' => 'label.titolo_documento', 'class' => 'gs-placeholder', 'style' => 'width:30em'],
          'label_attr' => ['class' => 'sr-only'],
          'required' => false))
        ->add('submit', SubmitType::class, array('label' => 'label.filtra',
          'attr' => ['class' => 'btn-primary']));
      return;
    }
    if (in_array($options['formMode'], ['B', 'H', 'D'])) {
      // form documenti BES
      $opzioniTipo = [];
      foreach ($options['values'][1] as $opt) {
        $opzioniTipo['label.documenti_bes_'.$opt] = $opt;
      }
      if (empty($options['values'][2])) {
        // scelta alunno
        $builder
          ->add('classe', EntityType::class, array('label' => 'label.classe',
            'class' => 'App\Entity\Classe',
            'choice_label' => function($obj) { return $obj->getAnno().'ª '.$obj->getSezione(); },
            'placeholder' => 'label.scegli_classe',
            'query_builder' => $fnSede,
            'group_by' => function($obj) { return $obj->getSede()->getCitta(); },
            'choice_attr' => function() { return ['class' => 'gs-no-placeholder']; },
            'attr' => ['class' => 'gs-placeholder'],
            'required' => false))
          ->add('alunno', HiddenType::class, array('label' => false,
            'required' => false));
      }
      $builder
        ->add('tipo', ChoiceType::class, array('label' => 'label.tipo_documenti',
          'choices' => $opzioniTipo,
          'placeholder' => 'label.scegli_tipo_documento',
          'choice_attr' => function() { return ['class' => 'gs-no-placeholder']; },
          'attr' => ['class' => 'gs-placeholder'],
          'required' => false))
        ->add('submit', SubmitType::class, array('label' => 'label.submit',
          'attr' => ['widget' => 'gs-button-start', 'class' => 'btn-primary']))
        ->add('cancel', ButtonType::class, array('label' => 'label.cancel',
          'attr' => ['widget' => 'gs-button-end',
            'onclick' => "location.href='".$options['returnUrl']."'"]));
      return;
    }
    // form vuoto per solo allegato
    $builder
      ->add('submit', SubmitType::class, array('label' => 'label.submit',
        'attr' => ['widget' => 'gs-button-start', 'class' => 'btn-primary']))
      ->add('cancel', ButtonType::class, array('label' => 'label.cancel',
        'attr' => ['widget' => 'gs-button-end',
          'onclick' => "location.href='".$options['returnUrl']."'"]));

  }

  /**
   * Configura le opzioni usate nel form
   *
   * @param OptionsResolver $resolver Gestore delle opzioni
   */
  public function configureOptions(OptionsResolver $resolver) {
    $resolver->setDefined('returnUrl');
    $resolver->setDefined('formMode');
    $resolver->setDefined('values');
    $resolver->setDefaults(array(
      'allow_extra_fields' => true,
      'data_class' => null));
  }

}
