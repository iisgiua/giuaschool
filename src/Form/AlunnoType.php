<?php
/**
 * giua@school
 *
 * Copyright (c) 2017-2020 Antonello Dessì
 *
 * @author    Antonello Dessì
 * @license   http://www.gnu.org/licenses/agpl.html AGPL
 * @copyright Antonello Dessì 2017-2020
 */


namespace App\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\Extension\Core\Type\ButtonType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\Extension\Core\Type\CollectionType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Doctrine\ORM\EntityRepository;
use App\Entity\Alunno;


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
      ->add('username', TextType::class, array('label' => 'label.username',
        'required' => true))
      ->add('email', TextType::class, array('label' => 'label.email',
        'attr' => ['widget' => 'gs-row-start'],
        'required' => true))
      ->add('email_genitore', TextType::class, array('label' => 'label.email_genitore',
        'data' => $options['dati'][0],
        'attr' => ['widget' => 'gs-row-end'],
        'mapped' => false,
        'required' => true))
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
      ->add('numeriTelefono', CollectionType::class, array('label' => 'label.numeri_telefono',
        'entry_options' => ['label'=>false],
        'allow_add' => true,
        'allow_delete' => true,
        'prototype' => true,
        'by_reference' => false,
        'required' => false))
      ->add('religione', ChoiceType::class, array('label' => 'label.religione',
        'choices' => array('label.religione_S' => 'S', 'label.religione_U' => 'U', 'label.religione_I' => 'I',
          'label.religione_D' => 'D', 'label.religione_A' => 'A'),
        'attr' => ['widget' => 'gs-row-start'],
        'required' => true))
      ->add('bes', ChoiceType::class, array('label' => 'label.bes',
        'choices' => array('label.bes_B' => 'B', 'label.bes_D' => 'D', 'label.bes_H' => 'H', 'label.bes_N' => 'N'),
        'attr' => ['widget' => 'gs-row-end'],
        'required' => true))
      ->add('noteBes', TextAreaType::class, array('label' => 'label.note_bes',
        'attr' => ['rows' => '3'],
        'required' => false))
      ->add('autorizzaEntrata', TextType::class, array('label' => 'label.autorizza_entrata',
        'attr' => ['widget' => 'gs-row-start'],
        'required' => false))
      ->add('autorizzaUscita', TextType::class, array('label' => 'label.autorizza_uscita',
        'attr' => ['widget' => 'gs-row-end'],
        'required' => false))
      ->add('note', TextAreaType::class, array('label' => 'label.note',
        'attr' => ['rows' => '3'],
        'required' => false))
      ->add('frequenzaEstero', ChoiceType::class, array('label' => 'label.frequenza_estero',
        'choices' => array('label.si' => true, 'label.no' => false),
        'expanded' => true,
        'multiple' => false,
        'label_attr' => ['class' => 'radio-inline'],
        'required' => true))
      ->add('credito3', IntegerType::class, array('label' => 'label.credito3',
        'attr' => ['min' => 0],
        'attr' => ['widget' => 'gs-row-start'],
        'required' => false))
      ->add('credito4', IntegerType::class, array('label' => 'label.credito4',
        'attr' => ['min' => 0],
        'attr' => ['widget' => 'gs-row-end'],
        'required' => false))
      ->add('classe', EntityType::class, array('label' => 'label.classe',
        'class' => 'App:Classe',
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
        'attr' => ['widget' => 'search'],
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
    $resolver->setDefined('dati');
    $resolver->setDefaults(array(
      'returnUrl' => null,
      'dati' => null,
      'data_class' => Alunno::class));
  }

}
