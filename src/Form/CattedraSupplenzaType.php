<?php
/*
 * SPDX-FileCopyrightText: 2017 I.I.S. Michele Giua - Cagliari - Assemini
 *
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */


namespace App\Form;

use App\Entity\Cattedra;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ButtonType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\OptionsResolver\OptionsResolver;


/**
 * CattedraSupplenzaType - form per la classe Cattedra nel caso di supplenza
 *
 * @author Antonello Dessì
 */
class CattedraSupplenzaType extends AbstractType {

  /**
   * Costruttore
   *
   * @param EntityManagerInterface $em Gestore delle entità
   */
  public function __construct(
      private readonly EntityManagerInterface $em)
  {
  }

  /**
   * Crea il form
   *
   * @param FormBuilderInterface $builder Gestore per la creazione del form
   * @param array $options Lista di opzioni per il form
   */
  public function buildForm(FormBuilderInterface $builder, array $options): void {
    // aggiunge campi al form
    $builder
      ->add('docente', ChoiceType::class, ['label' => 'label.docente',
        'choices' => $options['values'][0],
        'choice_value' => 'id',
        'placeholder' => 'label.choose_option',
        'choice_translation_domain' => false,
        'attr' => ['widget' => 'search'],
        'required' => true])
      ->add('docenteSupplenza', ChoiceType::class, ['label' => 'label.docente_supplenza',
        'choices' => $options['values'][0],
        'choice_value' => 'id',
        'placeholder' => 'label.choose_option',
        'choice_translation_domain' => false,
        'attr' => ['widget' => 'search'],
        'required' => true])
      ->add('lista', ChoiceType::class, ['label' => 'label.docente_supplenza_lista',
        'choices' => [],
        'multiple' => true,
        'expanded' => true,
        'required' => true])
      ->add('submit', SubmitType::class, ['label' => 'label.submit',
        'attr' => ['widget' => 'gs-button-start']])
      ->add('cancel', ButtonType::class, ['label' => 'label.cancel',
        'attr' => ['widget' => 'gs-button-end', 'onclick' => "location.href='".$options['return_url']."'"]]);
    // aggiunge evento
    $builder->addEventListener(FormEvents::PRE_SUBMIT,
      function (FormEvent $event): void {
        $data = $event->getData();
        $form = $event->getForm();
        if (!isset($data['lista']) || !isset($data['docenteSupplenza'])) {
          return;
        }
        $cattedraIds = $data['lista'];
        // carica solo le cattedre effettivamente inviate
        $cattedre = $this->em->getRepository(Cattedra::class)->createQueryBuilder('c')
          ->where('c.id IN (:ids) AND c.docente=:docente AND c.attiva=1')
          ->setParameter('ids', $cattedraIds)
          ->setParameter('docente', $data['docenteSupplenza'])
          ->getQuery()
          ->getResult();
        $choices = [];
        foreach ($cattedre as $cattedra) {
            $choices[(string) $cattedra] = $cattedra;
        }
        // ricrea il campo cattedre con le scelte valide inviate
        $form->add('lista', ChoiceType::class, ['label' => 'label.docente_supplenza_lista',
          'choices' => $choices,
          'choice_value' => 'id',
          'choice_translation_domain' => false,
          'multiple' => true,
          'expanded' => true,
          'required' => true]);
      });
  }

  /**
   * Configura le opzioni usate nel form
   *
   * @param OptionsResolver $resolver Gestore delle opzioni
   */
  public function configureOptions(OptionsResolver $resolver): void {
    $resolver->setDefined('return_url');
    $resolver->setDefined('values');
    $resolver->setDefaults([
      'values' => [],
      'return_url' => null,
      'data_class' => null]);
  }

}
