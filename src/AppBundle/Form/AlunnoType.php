<?php
/**
 * giua@school
 *
 * Copyright (c) 2017-2019 Antonello Dessì
 *
 * @author    Antonello Dessì
 * @license   http://www.gnu.org/licenses/agpl.html AGPL
 * @copyright Antonello Dessì 2017-2019
 */


namespace AppBundle\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\Extension\Core\Type\CollectionType;
use Symfony\Component\Form\Extension\Core\Type\NumberType;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Doctrine\ORM\EntityRepository;
use AppBundle\Entity\Alunno;


/**
 * AlunnoType - form per la classe Alunno
 */
class AlunnoType extends AbstractType {

  /**
   * Crea il form
   *
   * @param FormBuilderInterface $builder Gestore per la creazione del form
   * @param array $options Lista di opzioni per il form
   */
  public function buildForm(FormBuilderInterface $builder, array $options) {
    // aggiunge campi al form
    $builder
      ->add('nome', TextType::class, array('label' => 'label.nome',
        'required' => true))
      ->add('cognome', TextType::class, array('label' => 'label.cognome',
        'required' => true))
      ->add('sesso', ChoiceType::class, array('label' => 'label.sesso',
        'choices' => array('label.maschile' => 'M', 'label.femminile' => 'F'),
        'expanded' => true,
        'multiple' => false,
        'label_attr' => ['class' => 'radio-inline'],
        'required' => true))
      ->add('dataNascita', DateType::class, array('label' => 'label.data_nascita',
        'widget' => 'single_text',
        'html5' => false,
        'attr' => ['widget' => 'gs-picker'],
        'format' => 'dd/MM/yyyy',
        'required' => true))
      ->add('comuneNascita', TextType::class, array('label' => 'label.comune_nascita',
        'required' => true))
      ->add('codiceFiscale', TextType::class, array('label' => 'label.codice_fiscale',
        'required' => true))
      ->add('citta', TextType::class, array('label' => 'label.citta',
        'required' => false))
      ->add('indirizzo', TextType::class, array('label' => 'label.indirizzo',
        'required' => false))
      ->add('numeriTelefono', CollectionType::class, array('label' => 'label.numeri_telefono',
        'entry_options' => ['label'=>false],
        'allow_add' => true,
        'allow_delete' => true,
        'prototype' => true,
        'by_reference' => false,
        'required' => false))
      ->add('bes', ChoiceType::class, array('label' => 'label.bes',
        'choices' => array('label.bes_B' => 'B', 'label.bes_D' => 'D', 'label.bes_H' => 'H', 'label.bes_N' => 'N'),
        'expanded' => false,
        'multiple' => false,
        'required' => true))
      ->add('religione', ChoiceType::class, array('label' => 'label.religione',
        'choices' => array('label.religione_S' => 'S', 'label.religione_U' => 'U', 'label.religione_I' => 'I',
          'label.religione_D' => 'D', 'label.religione_M' => 'M'),
        'expanded' => false,
        'multiple' => false,
        'required' => true))
      ->add('frequenzaEstero', ChoiceType::class, array('label' => 'label.frequenza_estero',
        'choices' => array('label.si' => true, 'label.no' => false),
        'expanded' => true,
        'multiple' => false,
        'label_attr' => ['class' => 'radio-inline'],
        'required' => true))
      ->add('credito3', NumberType::class, array('label' => 'label.credito3',
        'scale' => 0,
        'required' => false))
      ->add('credito4', NumberType::class, array('label' => 'label.credito4',
        'scale' => 0,
        'required' => false))
      ->add('classe', EntityType::class, array('label' => 'label.classe',
        'class' => 'AppBundle:Classe',
        'choice_label' => function ($obj) {
            return $obj->getAnno().'ª '.$obj->getSezione().' - '.$obj->getCorso()->getNomeBreve();
          },
        'placeholder' => 'label.nessuna_classe',
        'query_builder' => function (EntityRepository $er) {
            return $er->createQueryBuilder('c')
              ->orderBy('c.anno,c.sezione', 'ASC');
          },
        'group_by' => function ($obj) {
            return $obj->getSede()->getCitta();
          },
        'required' => false));
  }

  /**
   * Configura le opzioni usate nel form
   *
   * @param OptionsResolver $resolver Gestore delle opzioni
   */
  public function configureOptions(OptionsResolver $resolver) {
    $resolver->setDefaults(array(
      'allow_extra_fields' => true,
      'data_class' => Alunno::class));
  }

}

