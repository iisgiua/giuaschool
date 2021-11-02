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

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\Extension\Core\Type\ButtonType;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Doctrine\ORM\EntityRepository;
use App\Entity\Ata;


/**
 * AtaType - form per la classe Ata
 */
class AtaType extends AbstractType {

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
        'attr' => ['widget' => 'gs-row-start'],
        'required' => true))
      ->add('cognome', TextType::class, array('label' => 'label.cognome',
        'attr' => ['widget' => 'gs-row-end'],
        'required' => true))
      ->add('sesso', ChoiceType::class, array('label' => 'label.sesso',
        'choices' => array('label.maschile' => 'M', 'label.femminile' => 'F'),
        'attr' => ['widget' => 'gs-row-start'],
        'required' => true))
      ->add('codiceFiscale', TextType::class, array('label' => 'label.codice_fiscale',
        'attr' => ['widget' => 'gs-row-end'],
        'required' => false))
      ->add('username', TextType::class, array('label' => 'label.username',
        'attr' => ['widget' => 'gs-row-start'],
        'required' => true))
      ->add('email', TextType::class, array('label' => 'label.email',
        'attr' => ['widget' => 'gs-row-end'],
        'required' => true))
      ->add('tipo', ChoiceType::class, array('label' => 'label.ata_tipo',
        'choices' => array('label.ata_tipo_D' => 'D', 'label.ata_tipo_A' => 'A', 'label.ata_tipo_T' => 'T',
           'label.ata_tipo_U' => 'U', 'label.ata_tipo_C' => 'C'),
        'attr' => ['widget' => 'gs-row-start'],
        'required' => true))
      ->add('segreteria', ChoiceType::class, array('label' => 'label.ata_segreteria',
        'choices' => array('label.si' => true, 'label.no' => false),
        'attr' => ['widget' => 'gs-row-end'],
        'required' => true))
      ->add('sede', EntityType::class, array('label' => 'label.sede',
        'class' => 'App:Sede',
        'choice_label' => function ($obj) {
            return $obj->getCitta();
          },
        'placeholder' => 'label.nessuna_sede',
        'query_builder' => function (EntityRepository $er) {
            return $er->createQueryBuilder('s')
              ->orderBy('s.ordinamento', 'ASC');
          },
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
      'data_class' => Ata::class));
  }

}
