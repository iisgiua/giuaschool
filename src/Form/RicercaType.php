<?php
/*
 * SPDX-FileCopyrightText: 2017 I.I.S. Michele Giua - Cagliari - Assemini
 *
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */


namespace App\Form;

use Doctrine\ORM\EntityRepository;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;


/**
 * RicercaType - form per filtro di ricerca
 *
 * @author Antonello Dessì
 */
class RicercaType extends AbstractType {

  /**
   * Crea il form
   *
   * @param FormBuilderInterface $builder Gestore per la creazione del form
   * @param array $options Lista di opzioni per il form
   */
  public function buildForm(FormBuilderInterface $builder, array $options) {
    if ($options['form_mode'] == 'ata') {
      // form ata
      $builder
        ->add('sede', ChoiceType::class, array('label' => 'label.sede',
          'data' => $options['values'][0],
          'choices' => $options['values'][3],
          'placeholder' => 'label.qualsiasi_sede',
          'choice_translation_domain' => false,
          'required' => false))
        ->add('cognome', TextType::class, array('label' => 'label.cognome',
          'data' => $options['values'][1],
          'required' => false))
        ->add('nome', TextType::class, array('label' => 'label.nome',
          'data' => $options['values'][2],
          'required' => false));
   } elseif ($options['form_mode'] == 'docenti-alunni') {
      // form docenti/alunni
      $builder
        ->add('classe', ChoiceType::class, array('label' => 'label.classe',
          'data' => $options['values'][0],
          'choices' => $options['values'][3],
          'placeholder' => 'label.qualsiasi_classe',
          'choice_translation_domain' => false,
          'attr' => ['widget' => 'search'],
          'required' => false))
        ->add('cognome', TextType::class, array('label' => 'label.cognome',
          'data' => $options['values'][1],
          'required' => false))
        ->add('nome', TextType::class, array('label' => 'label.nome',
          'data' => $options['values'][2],
          'required' => false));
    } elseif ($options['form_mode'] == 'utenti') {
      // form utenti
      $builder
        ->add('cognome', TextType::class, array('label' => 'label.cognome',
          'data' => $options['values'][0],
          'required' => false))
        ->add('nome', TextType::class, array('label' => 'label.nome',
          'data' => $options['values'][1],
          'required' => false));
    } elseif ($options['form_mode'] == 'cattedre') {
      // form cattedre
      $builder
        ->add('classe', ChoiceType::class, ['label' => 'label.classe',
          'data' => $options['values'][0],
          'choices' => $options['values'][3],
          'placeholder' => 'label.qualsiasi_classe',
          'choice_translation_domain' => false,
          'attr' => ['widget' => 'search'],
          'required' => false])
        ->add('materia', EntityType::class, array('label' => 'label.materia',
          'data' => $options['values'][1],
          'class' => 'App\Entity\Materia',
          'choice_label' => 'nome',
          'query_builder' => function (EntityRepository $er) {
            return $er->createQueryBuilder('c')
              ->where("c.tipo IN ('N','R','S','E')")
              ->orderBy('c.nome', 'ASC'); },
          'placeholder' => 'label.qualsiasi_materia',
          'choice_translation_domain' => false,
          'attr' => ['widget' => 'search'],
          'required' => false))
        ->add('docente', EntityType::class, array('label' => 'label.docente',
          'data' => $options['values'][2],
          'class' => 'App\Entity\Docente',
          'choice_label' => function ($obj) {
            return $obj->getCognome().' '.$obj->getNome().' ('.$obj->getUsername().')'; },
          'query_builder' => function (EntityRepository $er) {
            return $er->createQueryBuilder('d')
                ->where('d.abilitato=1 AND d NOT INSTANCE OF App\Entity\Preside')
                ->orderBy('d.cognome,d.nome,d.username', 'ASC'); },
          'placeholder' => 'label.qualsiasi_docente',
          'choice_translation_domain' => false,
          'attr' => ['widget' => 'search'],
          'required' => false));
    } elseif ($options['form_mode'] == 'docenti-sedi') {
      // form classe-docente
      $builder
        ->add('sede', EntityType::class, array('label' => 'label.sede',
          'data' => $options['values'][0],
          'class' => 'App\Entity\Sede',
          'choice_label' => 'citta',
          'query_builder' => function (EntityRepository $er) {
            return $er->createQueryBuilder('s')->orderBy('s.ordinamento', 'ASC'); },
          'placeholder' => 'label.qualsiasi_sede',
          'required' => false))
        ->add('classe', EntityType::class, array('label' => 'label.classe',
          'data' => $options['values'][1],
          'class' => 'App\Entity\Classe',
          'choice_label' => function ($obj) {
            return (is_object($obj) ? $obj->getAnno().'ª '.$obj->getSezione() : $obj); },
          'group_by' => 'sede.citta',
          'query_builder' => function (EntityRepository $er) {
            return $er->createQueryBuilder('c')->orderBy('c.anno,c.sezione', 'ASC'); },
          'placeholder' => 'label.qualsiasi_classe',
          'attr' => ['widget' => 'search'],
          'required' => false))
        ->add('docente', EntityType::class, array('label' => 'label.docente',
          'data' => $options['values'][2],
          'class' => 'App\Entity\Docente',
          'choice_label' => function ($obj) {
            return $obj->getCognome().' '.$obj->getNome().' ('.$obj->getUsername().')'; },
          'query_builder' => function (EntityRepository $er) {
            return $er->createQueryBuilder('d')
                ->where('d.abilitato=1 AND d NOT INSTANCE OF App\Entity\Preside')
                ->orderBy('d.cognome,d.nome,d.username', 'ASC'); },
          'placeholder' => 'label.qualsiasi_docente',
          'attr' => ['widget' => 'search'],
          'required' => false));
    } elseif ($options['form_mode'] == 'rappresentanti') {
      // form rappresentanti
      $builder
      ->add('tipo', ChoiceType::class, array('label' => 'label.tipo',
        'data' => $options['values'][0],
        'choices' => $options['values'][3],
        'placeholder' => 'label.tutti',
        'required' => false))
      ->add('cognome', TextType::class, array('label' => 'label.cognome',
        'data' => $options['values'][1],
        'required' => false))
      ->add('nome', TextType::class, array('label' => 'label.nome',
        'data' => $options['values'][2],
        'required' => false));
    }
    // pulsante filtro
    $builder
      ->add('submit', SubmitType::class, array('label' => 'label.filtra'));
  }

  /**
   * Configura le opzioni usate nel form
   *
   * @param OptionsResolver $resolver Gestore delle opzioni
   */
  public function configureOptions(OptionsResolver $resolver) {
    $resolver->setDefined('form_mode');
    $resolver->setDefined('values');
    $resolver->setDefaults([
      'form_mode' => 'ata',
      'values' => [],
      'data_class' => null]);
  }

}
