<?php
/*
 * SPDX-FileCopyrightText: 2017 I.I.S. Michele Giua - Cagliari - Assemini
 *
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */


namespace App\Form;

use App\Entity\Alunno;
use App\Form\MessageType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;


/**
 * AlunnoType - form per la classe Alunno
 *
 * @author Antonello Dessì
 */
class AlunnoType extends AbstractType {

  /**
   * Crea il form
   *
   * @param FormBuilderInterface $builder Gestore per la creazione del form
   * @param array $options Lista di opzioni per il form
   */
  public function buildForm(FormBuilderInterface $builder, array $options) {
    // form di modifica
    $builder
      ->add('nome', TextType::class, array('label' => 'label.nome',
        'attr' => ['widget' => 'gs-row-start'],
        'required' => true))
      ->add('cognome', TextType::class, array('label' => 'label.cognome',
        'attr' => ['widget' => 'gs-row-end'],
        'required' => true))
      ->add('sesso', ChoiceType::class, array('label' => 'label.sesso',
        'choices' => array('label.maschile' => 'M', 'label.femminile' => 'F'),
        'attr' => ['widget' => 'gs-row-start'],
        'required' => true))
      ->add('dataNascita', DateType::class, array('label' => 'label.data_nascita',
        'widget' => 'single_text',
        'html5' => false,
        'format' => 'dd/MM/yyyy',
        'attr' => ['widget' => 'gs-row-end'],
        'required' => true))
      ->add('comuneNascita', TextType::class, array('label' => 'label.comune_nascita',
        'attr' => ['widget' => 'gs-row-start'],
        'required' => true))
      ->add('codiceFiscale', TextType::class, array('label' => 'label.codice_fiscale',
        'attr' => ['widget' => 'gs-row-end'],
        'required' => true))
      ->add('citta', TextType::class, array('label' => 'label.citta',
        'attr' => ['widget' => 'gs-row-start'],
        'required' => false))
      ->add('indirizzo', TextType::class, array('label' => 'label.indirizzo',
        'attr' => ['widget' => 'gs-row-end'],
        'required' => false))
      ->add('religione', ChoiceType::class, array('label' => 'label.religione',
        'choices' => array('label.religione_S' => 'S', 'label.religione_U' => 'U', 'label.religione_I' => 'I',
          'label.religione_D' => 'D', 'label.religione_A' => 'A'),
        'attr' => ['widget' => 'gs-row-start'],
        'required' => true))
      ->add('bes', ChoiceType::class, array('label' => 'label.bes',
        'choices' => array('label.bes_B' => 'B', 'label.bes_D' => 'D', 'label.bes_H' => 'H', 'label.bes_A' => 'A', 'label.bes_N' => 'N'),
        'attr' => ['widget' => 'gs-row-end'],
        'required' => true))
      ->add('noteBes', MessageType::class, array('label' => 'label.note_bes',
        'attr' => ['rows' => '3'],
        'required' => false))
      ->add('credito3', IntegerType::class, array('label' => 'label.credito3',
        'attr' => ['min' => 0, 'widget' => 'gs-row-start'],
        'required' => false))
      ->add('credito4', IntegerType::class, array('label' => 'label.credito4',
        'attr' => ['min' => 0, 'widget' => 'gs-row-end'],
        'required' => false))
      ->add('classe', ChoiceType::class, ['label' => 'label.classe',
        'choices' => $options['values'][0],
        'choice_value' => 'id',
        'placeholder' => 'label.nessuna_classe',
        'choice_translation_domain' => false,
        'attr' => ['widget' => 'gs-row-start'],
        'required' => false])
      ->add('frequenzaEstero', ChoiceType::class, array('label' => 'label.frequenza_estero',
        'choices' => array('label.si' => true, 'label.no' => false),
        'expanded' => true,
        'multiple' => false,
        'label_attr' => ['class' => 'radio-inline'],
        'attr' => ['widget' => 'gs-row-end'],
        'required' => true))
      ->add('spid', ChoiceType::class, array('label' => 'label.spid',
        'choices' => array('label.si' => true, 'label.no' => false),
        'expanded' => true,
        'multiple' => false,
        'label_attr' => ['class' => 'radio-inline'],
        'required' => true))
      ->add('username', TextType::class, array('label' => 'label.username',
        'attr' => ['widget' => 'gs-row-start'],
        'required' => true))
      ->add('email', TextType::class, array('label' => 'label.email',
        'attr' => ['widget' => 'gs-row-end'],
        'required' => true));
  }

  /**
   * Configura le opzioni usate nel form
   *
   * @param OptionsResolver $resolver Gestore delle opzioni
   */
  public function configureOptions(OptionsResolver $resolver) {
    $resolver->setDefined('values');
    $resolver->setDefaults([
      'values' => [],
      'data_class' => Alunno::class]);
  }

}
