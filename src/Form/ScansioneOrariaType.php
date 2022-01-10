<?php
/**
 * giua@school
 *
 * Copyright (c) 2017-2022 Antonello Dessì
 *
 * @author    Antonello Dessì
 * @license   http://www.gnu.org/licenses/agpl.html AGPL
 * @copyright Antonello Dessì 2017-2022
 */


namespace App\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\TimeType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\ButtonType;
use App\Entity\ScansioneOraria;


/**
 * ScansioneOrariaType - form per la classe ScansioneOraria
 */
class ScansioneOrariaType extends AbstractType {

  /**
   * Crea il form
   *
   * @param FormBuilderInterface $builder Gestore per la creazione del form
   * @param array $options Lista di opzioni per il form
   */
  public function buildForm(FormBuilderInterface $builder, array $options) {
    // aggiunge campi al form
    $builder
      ->add('visibile', HiddenType::class, array('label' => false,
        'required' => true,
        'mapped' => false))
      ->add('ora', TextType::class, array('label' => 'label.ora',
        'attr' => ['widget' => 'gs-row-start', 'class' => 'border-0 pl-1 pr-1 text-center'],
        'row_attr' => ['class' => 'col-1'],
        'disabled' => true,
        'required' => true))
      ->add('inizio', TimeType::class, array('label' => 'label.ora_inizio',
        'widget' => 'single_text',
        'html5' => false,
        'attr' => ['widget' => 'gs-row-inline'],
        'row_attr' => ['class' => 'mt-2'],
        'required' => true))
      ->add('fine', TimeType::class, array('label' => 'label.ora_fine',
        'widget' => 'single_text',
        'html5' => false,
        'attr' => ['widget' => 'gs-row-inline'],
        'row_attr' => ['class' => 'mt-2'],
        'required' => true))
      ->add('durata', ChoiceType::class, array('label' => 'label.durata',
        'choices' => array('1' => 1.0, '0,5' => 0.5, '1,5' => 1.5),
        'choice_translation_domain' => false,
        'attr' => ['widget' => 'gs-row-inline'],
        'row_attr' => ['class' => 'col-2'],
        'required' => true))
      ->add('delete', ButtonType::class, array('label' => 'icon.delete',
        'attr' => ['widget' => 'gs-row-end',
          'class' => 'btn-danger btn-xs gs-remove-item',
          'title' => 'label.cancella_elemento',
          'label_html' => true],
        'row_attr' => ['class' => 'col-1']));
  }

  /**
   * Configura le opzioni usate nel form
   *
   * @param OptionsResolver $resolver Gestore delle opzioni
   */
  public function configureOptions(OptionsResolver $resolver) {
    $resolver->setDefaults([
      'data_class' => ScansioneOraria::class]);
  }

}
