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

use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Doctrine\ORM\EntityRepository;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;


/**
 * RicercaType - form per filtro di ricerca
 */
class RicercaType extends AbstractType {

  /**
   * Crea il form
   *
   * @param FormBuilderInterface $builder Gestore per la creazione del form
   * @param array $options Lista di opzioni per il form
   */
  public function buildForm(FormBuilderInterface $builder, array $options) {
    if ($options['formMode'] == 'ata') {
      // form ata
      $builder
        ->add('cognome', TextType::class, array('label' => 'label.cognome',
          'data' => $options['dati'][0],
          'required' => false))
        ->add('nome', TextType::class, array('label' => 'label.nome',
          'data' => $options['dati'][1],
          'required' => false))
        ->add('sede', ChoiceType::class, array('label' => 'label.sede',
          'data' => $options['dati'][2],
          'choices' => $options['dati'][3],
          'choice_label' => function ($obj) use ($options) {
            return (is_object($obj) ? $obj->getCitta() :
              $options['dati'][4]); },
          'choice_value' => function ($obj) {
            return (is_object($obj)  ? $obj->getId() : $obj); },
          'placeholder' => 'label.qualsiasi_sede',
          'choice_translation_domain' => false,
          'required' => false));
    } elseif ($options['formMode'] == 'docenti-alunni') {
      // form docenti
      $builder
        ->add('cognome', TextType::class, array('label' => 'label.cognome',
          'data' => $options['dati'][0],
          'required' => false))
        ->add('nome', TextType::class, array('label' => 'label.nome',
          'data' => $options['dati'][1],
          'required' => false))
        ->add('classe', ChoiceType::class, array('label' => 'label.classe',
          'data' => $options['dati'][2],
          'choices' => $options['dati'][3],
          'choice_label' => function ($obj) use ($options) {
            return (is_object($obj) ? $obj->getAnno().'ª '.$obj->getSezione() :
              $options['dati'][4]); },
          'choice_value' => function ($obj) {
            return (is_object($obj)  ? $obj->getId() : $obj); },
          'group_by' => function ($obj) {
            return (is_object($obj) ? $obj->getSede()->getCitta() : null); },
          'placeholder' => 'label.qualsiasi_classe',
          'choice_translation_domain' => false,
          'attr' => ['widget' => 'search'],
          'required' => false));
    } elseif ($options['formMode'] == 'utenti') {
      // form docenti
      $builder
        ->add('cognome', TextType::class, array('label' => 'label.cognome',
          'data' => $options['dati'][0],
          'required' => false))
        ->add('nome', TextType::class, array('label' => 'label.nome',
          'data' => $options['dati'][1],
          'required' => false));
    } elseif ($options['formMode'] == 'cattedre') {
      // form cattedre
      $builder
        ->add('classe', EntityType::class, array('label' => 'label.classe',
          'data' => $options['dati'][0],
          'class' => 'App:Classe',
          'choice_label' => function ($obj) {
            return (is_object($obj) ? $obj->getAnno().'ª '.$obj->getSezione() : $obj); },
          'group_by' => function ($obj) {
            return (is_object($obj) ? $obj->getSede()->getCitta() : null); },
          'query_builder' => function (EntityRepository $er) {
            return $er->createQueryBuilder('c')->orderBy('c.anno,c.sezione', 'ASC'); },
          'placeholder' => 'label.qualsiasi_classe',
          'choice_translation_domain' => false,
          'attr' => ['widget' => 'search'],
          'required' => false))
        ->add('materia', EntityType::class, array('label' => 'label.materia',
          'data' => $options['dati'][1],
          'class' => 'App:Materia',
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
          'data' => $options['dati'][2],
          'class' => 'App:Docente',
          'choice_label' => function ($obj) {
            return $obj->getCognome().' '.$obj->getNome().' ('.$obj->getUsername().')'; },
          'query_builder' => function (EntityRepository $er) {
            return $er->createQueryBuilder('d')
                ->where('d.abilitato=1 AND d NOT INSTANCE OF App:Preside')
                ->orderBy('d.cognome,d.nome,d.username', 'ASC'); },
          'placeholder' => 'label.qualsiasi_docente',
          'choice_translation_domain' => false,
          'attr' => ['widget' => 'search'],
          'required' => false));
    } elseif ($options['formMode'] == 'docenti-sedi') {
      // form classe-docente
      $builder
        ->add('sede', EntityType::class, array('label' => 'label.sede',
          'data' => $options['dati'][0],
          'class' => 'App:Sede',
          'choice_label' => 'citta',
          'query_builder' => function (EntityRepository $er) {
            return $er->createQueryBuilder('s')->orderBy('s.ordinamento', 'ASC'); },
          'placeholder' => 'label.qualsiasi_sede',
          'required' => false))
        ->add('classe', EntityType::class, array('label' => 'label.classe',
          'data' => $options['dati'][1],
          'class' => 'App:Classe',
          'choice_label' => function ($obj) {
            return (is_object($obj) ? $obj->getAnno().'ª '.$obj->getSezione() : $obj); },
          'group_by' => 'sede.citta',
          'query_builder' => function (EntityRepository $er) {
            return $er->createQueryBuilder('c')->orderBy('c.anno,c.sezione', 'ASC'); },
          'placeholder' => 'label.qualsiasi_classe',
          'attr' => ['widget' => 'search'],
          'required' => false))
        ->add('docente', EntityType::class, array('label' => 'label.docente',
          'data' => $options['dati'][2],
          'class' => 'App:Docente',
          'choice_label' => function ($obj) {
            return $obj->getCognome().' '.$obj->getNome().' ('.$obj->getUsername().')'; },
          'query_builder' => function (EntityRepository $er) {
            return $er->createQueryBuilder('d')
                ->where('d.abilitato=1 AND d NOT INSTANCE OF App:Preside')
                ->orderBy('d.cognome,d.nome,d.username', 'ASC'); },
          'placeholder' => 'label.qualsiasi_docente',
          'attr' => ['widget' => 'search'],
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
    $resolver->setDefined('formMode');
    $resolver->setDefined('dati');
    $resolver->setDefaults(array(
      'formMode' => 'ata',
      'dati' => null,
      'data_class' => null));
  }

}
