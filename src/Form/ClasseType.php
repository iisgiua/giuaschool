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
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\Extension\Core\Type\ButtonType;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Doctrine\ORM\EntityRepository;
use App\Entity\Classe;


/**
 * ClasseType - form per la classe Classe
 */
class ClasseType extends AbstractType {

  /**
   * Crea il form
   *
   * @param FormBuilderInterface $builder Gestore per la creazione del form
   * @param array $options Lista di opzioni per il form
   */
  public function buildForm(FormBuilderInterface $builder, array $options) {
    // aggiunge campi al form
    $builder
      ->add('anno', ChoiceType::class, array('label' => 'label.classe_anno',
        'choices' => array('1' => 1, '2' => 2, '3' => 3, '4' => 4, '5' => 5),
        'attr' => ['widget' => 'gs-row-start'],
        'required' => true))
      ->add('sezione', ChoiceType::class, array('label' => 'label.classe_sezione',
        'choices' => array_combine(range('A', 'Z'), range('A', 'Z')),
        'attr' => ['widget' => 'gs-row-end'],
        'required' => true))
      ->add('corso', EntityType::class, array('label' => 'label.corso',
        'class' => 'App:Corso',
        'choice_label' => function ($obj) {
            return $obj->getNomeBreve();
          },
        'query_builder' => function (EntityRepository $er) {
            return $er->createQueryBuilder('c')
              ->orderBy('c.nomeBreve', 'ASC');
          },
        'attr' => ['widget' => 'gs-row-start'],
        'required' => true))
      ->add('sede', EntityType::class, array('label' => 'label.sede',
        'class' => 'App:Sede',
        'choice_label' => function ($obj) {
            return $obj->getNomeBreve();
          },
        'query_builder' => function (EntityRepository $er) {
            return $er->createQueryBuilder('s')
              ->orderBy('s.ordinamento', 'ASC');
          },
        'attr' => ['widget' => 'gs-row-end'],
        'required' => true))
      ->add('coordinatore', EntityType::class, array('label' => 'label.coordinatore',
        'class' => 'App:Docente',
        'placeholder' => 'label.nessuno',
        'choice_label' => function ($obj) {
            return $obj->getNome().' '.$obj->getCognome();
          },
        'query_builder' => function (EntityRepository $er) {
            return $er->createQueryBuilder('d')
              ->orderBy('d.cognome,d.nome', 'ASC');
          },
        'attr' => ['widget' => 'gs-row-start'],
        'required' => false))
      ->add('segretario', EntityType::class, array('label' => 'label.segretario',
        'class' => 'App:Docente',
        'placeholder' => 'label.nessuno',
        'choice_label' => function ($obj) {
            return $obj->getNome().' '.$obj->getCognome();
          },
        'query_builder' => function (EntityRepository $er) {
            return $er->createQueryBuilder('d')
              ->orderBy('d.cognome,d.nome', 'ASC');
          },
        'attr' => ['widget' => 'gs-row-end'],
        'required' => false))
      ->add('oreSettimanali', IntegerType::class, array('label' => 'label.ore_classe',
        'required' => true))
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
      'data_class' => Classe::class));
  }

}
