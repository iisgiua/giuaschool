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
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\Extension\Core\Type\ButtonType;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Doctrine\ORM\EntityRepository;
use App\Entity\Cattedra;


/**
 * CattedraType - form per la classe Cattedra
 *
 * @author Antonello Dessì
 */
class CattedraType extends AbstractType {

  /**
   * Crea il form
   *
   * @param FormBuilderInterface $builder Gestore per la creazione del form
   * @param array $options Lista di opzioni per il form
   */
  public function buildForm(FormBuilderInterface $builder, array $options) {
    // aggiunge campi al form
    $builder
      ->add('classe', EntityType::class, array('label' => 'label.classe',
        'class' => 'App\Entity\Classe',
        'choice_label' => function ($obj) {
          return (is_object($obj) ? $obj->getAnno().'ª '.$obj->getSezione() : $obj); },
        'group_by' => 'sede.citta',
        'query_builder' => function (EntityRepository $er) {
          return $er->createQueryBuilder('c')->orderBy('c.anno,c.sezione', 'ASC'); },
        'placeholder' => 'label.choose_option',
        'attr' => ['widget' => 'search'],
        'required' => true))
      ->add('materia', EntityType::class, array('label' => 'label.materia',
        'class' => 'App\Entity\Materia',
        'choice_label' => 'nome',
        'query_builder' => function (EntityRepository $er) {
          return $er->createQueryBuilder('c')
            ->where("c.tipo IN ('N','R','S','E')")
            ->orderBy('c.nome', 'ASC'); },
        'placeholder' => 'label.choose_option',
        'attr' => ['widget' => 'search'],
        'required' => true))
      ->add('alunno', EntityType::class, array('label' => 'label.alunno_H',
        'class' => 'App\Entity\Alunno',
        'choice_label' => function ($obj) {
          return $obj->getCognome().' '.$obj->getNome().' ('.$obj->getDataNascita()->format('d/m/Y').')'; },
        'query_builder' => function (EntityRepository $er) {
          return $er->createQueryBuilder('a')
            ->where("a.bes='H' AND a.abilitato=1")
            ->orderBy('a.cognome,a.nome,a.dataNascita', 'ASC'); },
        'placeholder' => 'label.choose_option',
        'attr' => ['widget' => 'search'],
        'required' => false))
      ->add('docente', EntityType::class, array('label' => 'label.docente',
        'class' => 'App\Entity\Docente',
        'choice_label' => function ($obj) {
          return $obj->getCognome().' '.$obj->getNome().' ('.$obj->getUsername().')'; },
        'query_builder' => function (EntityRepository $er) {
          return $er->createQueryBuilder('d')
            ->where('d.abilitato=1 AND d NOT INSTANCE OF App\Entity\Preside')
            ->orderBy('d.cognome,d.nome,d.username', 'ASC'); },
        'placeholder' => 'label.choose_option',
        'attr' => ['widget' => 'search'],
        'required' => true))
      ->add('tipo', ChoiceType::class, array('label' => 'label.tipo',
        'choices' => array('label.tipo_N' => 'N', 'label.tipo_I' => 'I', 'label.tipo_P' => 'P',
          'label.tipo_A' => 'A'),
        'expanded' => true,
        'multiple' => false,
        'label_attr' => ['class' => 'radio-inline'],
        'attr' => ['widget' => 'gs-row-start'],
        'required' => true))
      ->add('supplenza', CheckboxType::class, array('label' => 'label.supplenza',
        'attr' => ['widget' => 'gs-row-end'],
        'required' => false))
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
      'data_class' => Cattedra::class));
  }

}
