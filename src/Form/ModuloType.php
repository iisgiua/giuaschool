<?php
/*
 * SPDX-FileCopyrightText: 2017 I.I.S. Michele Giua - Cagliari - Assemini
 *
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */


namespace App\Form;

use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Doctrine\ORM\EntityRepository;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\Extension\Core\Type\TimeType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\Extension\Core\Type\ButtonType;


/**
 * ModuloType - form per varie procedure senza entità di riferimento
 *
 * @author Antonello Dessì
 */
class ModuloType extends AbstractType {

  /**
   * Crea il form
   *
   * @param FormBuilderInterface $builder Gestore per la creazione del form
   * @param array $options Lista di opzioni per il form
   */
  public function buildForm(FormBuilderInterface $builder, array $options) {
    if ($options['formMode'] == 'importa') {
      // form importa
      $builder
        ->add('database', TextType::class, array('label' => 'label.database_precedente',
          'required' => true))
        ->add('directory', TextType::class, array('label' => 'label.directory_precedente',
          'required' => true))
        ->add('dati', ChoiceType::class, array('label' => 'label.importa_dati',
          'data' => ['I','C','L','A','P','E','X'],
          'choices' => ['label.importa_istituto' => 'I', 'label.importa_corsi' => 'C',
            'label.importa_classi' => 'L', 'label.importa_alunni' => 'A',
            'label.importa_personale' => 'P', 'label.importa_esiti' => 'E',
            'label.importa_scrutini_rinviati' => 'X'],
          'expanded' => true,
          'multiple' => true,
          'required' => false));
    } elseif ($options['formMode'] == 'archivia') {
      // form archivia
      $builder
        ->add('docente', ChoiceType::class, array('label' => 'label.registro_docente',
          'choices' => array_merge([-1], $options['dati'][0]),
          'choice_label' => function ($obj, $val) use ($options) {
              return (is_object($obj) ? $obj->getCognome().' '.$obj->getNome() :
                $options['dati'][3]); },
          'choice_value' => function ($obj) {
              return (is_object($obj) ? $obj->getId() : $obj); },
          'placeholder' => 'label.nessuno',
          'choice_translation_domain' => false,
          'attr' => ['widget' => 'search'],
          'required' => false))
        ->add('sostegno', ChoiceType::class, array('label' => 'label.registro_sostegno',
          'choices' => array_merge([-1], $options['dati'][1]),
          'choice_label' => function ($obj, $val) use ($options) {
              return (is_object($obj) ? $obj->getCognome().' '.$obj->getNome() :
                $options['dati'][3]); },
          'choice_value' => function ($obj) {
              return (is_object($obj) ? $obj->getId() : $obj); },
          'placeholder' => 'label.nessuno',
          'choice_translation_domain' => false,
          'attr' => ['widget' => 'search'],
          'required' => false))
        ->add('classe', ChoiceType::class, array('label' => 'label.registro_classe',
          'choices' => array_merge([-1], $options['dati'][2]),
          'choice_label' => function ($obj, $val) use ($options) {
              return (is_object($obj) ? $obj->getAnno().'ª '.$obj->getSezione() :
                $options['dati'][4]); },
          'choice_value' => function ($obj) {
              return (is_object($obj) ? $obj->getId() : $obj); },
          'group_by' => function ($obj) {
              return (is_object($obj) ? $obj->getSede()->getCitta() : null); },
          'placeholder' => 'label.nessuno',
          'choice_translation_domain' => false,
          'attr' => ['widget' => 'search'],
          'required' => false))
        ->add('scrutinio', ChoiceType::class, array('label' => 'label.documenti_scrutinio',
          'choices' => array_merge([-1], $options['dati'][2]),
          'choice_label' => function ($obj, $val) use ($options) {
              return (is_object($obj) ? $obj->getAnno().'ª '.$obj->getSezione() :
                $options['dati'][4]); },
          'choice_value' => function ($obj) {
              return (is_object($obj) ? $obj->getId() : $obj); },
          'group_by' => function ($obj) {
              return (is_object($obj) ? $obj->getSede()->getCitta() : null); },
          'placeholder' => 'label.nessuno',
          'choice_translation_domain' => false,
          'attr' => ['widget' => 'search'],
          'required' => false))
        ->add('circolare', ChoiceType::class, array('label' => 'label.archivio_circolari',
          'choices' => ['label.no' => false, 'label.si' => true],
          'required' => true));
    } elseif ($options['formMode'] == 'staff') {
      $builder
        ->add('docente', EntityType::class, array('label' => 'label.docente',
          'data' => $options['dati'][0],
          'class' => 'App\Entity\Docente',
          'choice_label' => function ($obj) {
              return $obj->getCognome().' '.$obj->getNome().' ('.$obj->getUsername().')'; },
          'placeholder' => 'label.choose_option',
          'query_builder' => function (EntityRepository $er) {
            return $er->createQueryBuilder('d')
              ->where('d.abilitato=1 AND d NOT INSTANCE OF App\Entity\Preside')
              ->orderBy('d.cognome,d.nome,d.username', 'ASC'); },
          'attr' => ['widget' => 'search'],
          'disabled' => ($options['dati'][0] != null),
          'required' => true))
        ->add('sede', EntityType::class, array('label' => 'label.sede',
          'data' => $options['dati'][1],
          'class' => 'App\Entity\Sede',
          'choice_label' => 'citta',
          'placeholder' => 'label.qualsiasi_sede',
          'query_builder' => function (EntityRepository $er) {
            return $er->createQueryBuilder('s')
              ->orderBy('s.ordinamento', 'ASC'); },
          'required' => false));
    } elseif ($options['formMode'] == 'coordinatori') {
      $builder
        ->add('classe', EntityType::class, array('label' => 'label.classe',
          'data' => $options['dati'][0],
          'class' => 'App\Entity\Classe',
          'query_builder' => function (EntityRepository $er) {
            return $er->createQueryBuilder('c')
              ->orderBy('c.anno,c.sezione', 'ASC'); },
          'choice_label' => function ($obj, $val) {
              return (is_object($obj) ? $obj->getAnno().'ª '.$obj->getSezione() : $obj); },
          'group_by' => function ($obj) {
              return (is_object($obj) ? $obj->getSede()->getCitta() : null); },
          'placeholder' => 'label.choose_option',
          'choice_translation_domain' => false,
          'attr' => ['widget' => 'search'],
          'disabled' => ($options['dati'][0] != null),
          'required' => true))
        ->add('docente', EntityType::class, array('label' => 'label.docente',
          'data' => $options['dati'][1],
          'class' => 'App\Entity\Docente',
          'query_builder' => function (EntityRepository $er) {
            return $er->createQueryBuilder('d')
              ->where('d.abilitato=1 AND d NOT INSTANCE OF App\Entity\Preside')
              ->orderBy('d.cognome,d.nome,d.username', 'ASC'); },
          'choice_label' => function ($obj) {
              return $obj->getCognome().' '.$obj->getNome().' ('.$obj->getUsername().')'; },
          'placeholder' => 'label.choose_option',
          'attr' => ['widget' => 'search'],
          'required' => true));
    } elseif ($options['formMode'] == 'log') {
      $builder
        ->add('data', DateType::class, array('label' => 'label.data',
          'data' => $options['dati'][0],
          'widget' => 'single_text',
          'html5' => false,
          'format' => 'dd/MM/yyyy',
          'attr' => ['widget' => 'gs-row-start'],
          'required' => true))
        ->add('ora', TimeType::class, array('label' => 'label.ora_inizio',
          'data' => $options['dati'][1],
          'widget' => 'single_text',
          'html5' => false,
          'attr' => ['widget' => 'gs-row-end'],
          'required' => true));
    }
    // aggiunge pulsanti al form
    if ($options['returnUrl']) {
      $builder
        ->add('submit', SubmitType::class, array('label' => 'label.submit',
          'attr' => ['widget' => 'gs-button-start']))
        ->add('cancel', ButtonType::class, array('label' => 'label.cancel',
          'attr' => ['widget' => 'gs-button-end', 'onclick' => "location.href='".$options['returnUrl']."'"]));
    } else {
      $builder
        ->add('submit', SubmitType::class, array('label' => 'label.submit'));
    }
  }

  /**
   * Configura le opzioni usate nel form
   *
   * @param OptionsResolver $resolver Gestore delle opzioni
   */
  public function configureOptions(OptionsResolver $resolver) {
    $resolver->setDefined('formMode');
    $resolver->setDefined('returnUrl');
    $resolver->setDefined('dati');
    $resolver->setDefaults(array(
      'formMode' => 'importa',
      'returnUrl' => null,
      'dati' => null,
      'data_class' => null));
  }

}
