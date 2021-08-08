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
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\ButtonType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Doctrine\ORM\EntityRepository;
use App\Entity\Documento;


/**
 * DocumentoType - form per i documenti
 */
class DocumentoType extends AbstractType {

  /**
   * Crea il form
   *
   * @param FormBuilderInterface $builder Gestore per la creazione del form
   * @param array $options Lista di opzioni per il form
   */
  public function buildForm(FormBuilderInterface $builder, array $options) {
    if ($options['formMode'] == 'filtroDocenti') {
      // form filtro documenti docenti
      $fnSede = function(EntityRepository $er) {
        return $er->createQueryBuilder('c')
          ->orderBy('c.anno,c.sezione', 'ASC'); };
      if ($options['values'][3]) {
        // filtro su sede
        $fnSede = function(EntityRepository $er) use ($options) {
          return $er->createQueryBuilder('c')
            ->where('c.sede=:sede')
            ->setParameter('sede', $options['values'][3])
            ->orderBy('c.anno,c.sezione', 'ASC'); };
      }
      $builder
        ->add('filtro', ChoiceType::class, array('label' => 'label.filtro_documenti',
          'data' => $options['values'][0],
          'choices' => ['label.documenti_presenti' => 'D', 'label.documenti_mancanti' => 'M',
            'label.documenti_tutti' => 'T'],
          'label_attr' => ['class' => 'sr-only'],
          'choice_attr' => function($val) { return ['class' => 'gs-no-placeholder']; },
          'attr' => ['class' => 'gs-placeholder'],
          'required' => true))
        ->add('tipo', ChoiceType::class, array('label' => 'label.tipo_documenti',
          'data' => $options['values'][1],
          'choices' => ['label.piani' => 'L', 'label.programmi' => 'P', 'label.relazioni' => 'R',
            'label.maggio' => 'M'],
          'label_attr' => ['class' => 'sr-only'],
          'choice_attr' => function($val) { return ['class' => 'gs-no-placeholder']; },
          'attr' => ['class' => 'gs-placeholder'],
          'required' => true))
        ->add('classe', EntityType::class, array('label' => 'label.classe',
          'data' => $options['values'][2],
          'class' => 'App:Classe',
          'choice_label' => function($obj) { return $obj->getAnno().'ª '.$obj->getSezione(); },
          'placeholder' => 'label.tutte_classi',
          'query_builder' => $fnSede,
          'group_by' => function($obj) { return $obj->getSede()->getCitta(); },
          'label_attr' => ['class' => 'sr-only'],
          'choice_attr' => function($val) { return ['class' => 'gs-no-placeholder']; },
          'attr' => ['class' => 'gs-placeholder'],
          'required' => false))
        ->add('submit', SubmitType::class, array('label' => 'label.filtra',
          'attr' => ['class' => 'btn-primary']));
    } else {
      // form vuoto per solo allegato
      $builder
        ->add('submit', SubmitType::class, array('label' => 'label.submit',
          'attr' => ['widget' => 'gs-button-start', 'class' => 'btn-primary']))
        ->add('cancel', ButtonType::class, array('label' => 'label.cancel',
          'attr' => ['widget' => 'gs-button-end',
            'onclick' => "location.href='".$options['returnUrl']."'"]));
    }
  }

  /**
   * Configura le opzioni usate nel form
   *
   * @param OptionsResolver $resolver Gestore delle opzioni
   */
  public function configureOptions(OptionsResolver $resolver) {
    $resolver->setDefined('returnUrl');
    $resolver->setDefined('formMode');
    $resolver->setDefined('values');
    $resolver->setDefaults(array(
      'data_class' => null));
  }

}
