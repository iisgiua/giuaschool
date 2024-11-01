<?php
/*
 * SPDX-FileCopyrightText: 2017 I.I.S. Michele Giua - Cagliari - Assemini
 *
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */


namespace App\Form;

use App\Entity\Alunno;
use App\Entity\PropostaVoto;
use App\Form\MessageType;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\CallbackTransformer;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;


/**
 * PropostaVotoType - form per la classe PropostaVoto
 *
 * @author Antonello Dessì
 */
class PropostaVotoType extends AbstractType {

  //==================== METODI DELLA CLASSE ====================

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
      ->add('alunno', HiddenType::class)
      ->add('unico', HiddenType::class)
      ->add('recupero', ChoiceType::class, ['label' => false,
        'choices' => ['label.recupero_A' => 'A', 'label.recupero_P' => 'P',
          'label.recupero_S' => 'S', 'label.recupero_C' => 'C', 'label.recupero_I' => 'I',
          'label.recupero_R' => 'R', 'label.recupero_N' => 'N'],
        'placeholder' => 'label.scegli_recupero',
        'expanded' => false,
        'multiple' => false,
        'choice_attr' => fn() => ['class' => 'gs-no-placeholder'],
        'attr' => ['class' => 'gs-placeholder'],
        'required' => false])
      ->add('debito', MessageType::class, ['label' => false,
        'trim' => true,
        'attr' => ['rows' => '3'],
        'required' => false]);
    // aggiunge data transform
    $builder->get('alunno')->addModelTransformer(new CallbackTransformer(
      fn($alunno) => $alunno->getId(),
      fn($id) => $this->em->getRepository(Alunno::class)->find($id)));
  }

  /**
   * Configura le opzioni usate nel form
   *
   * @param OptionsResolver $resolver Gestore delle opzioni
   */
  public function configureOptions(OptionsResolver $resolver): void {
    $resolver->setDefaults([
      'data_class' => PropostaVoto::class]);
  }

}
