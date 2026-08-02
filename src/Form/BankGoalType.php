<?php

namespace App\Form;

use App\Entity\BankGoal;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\Extension\Core\Type\NumberType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\GreaterThan;
use Symfony\Component\Validator\Constraints\NotBlank;

final class BankGoalType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('name', TextType::class, [
                'label' => 'Objectif',
                'constraints' => [new NotBlank()],
                'attr' => ['placeholder' => 'Ex: Vacances, matelas de securite...'],
            ])
            ->add('targetAmount', NumberType::class, [
                'label' => 'Montant cible (€)',
                'scale' => 2,
                'constraints' => [new GreaterThan(0)],
            ])
            ->add('linkedAccount', ChoiceType::class, [
                'label' => 'Compte lie (optionnel)',
                'required' => false,
                'placeholder' => '— Suivi manuel —',
                'choices' => $options['account_choices'],
                'help' => 'Si un compte est choisi, le montant actuel suit automatiquement son solde.',
            ])
            ->add('currentAmount', NumberType::class, [
                'label' => 'Montant deja epargne (€)',
                'scale' => 2,
                'required' => false,
                'empty_data' => '0',
            ])
            ->add('targetDate', DateType::class, [
                'label' => 'Date cible (optionnel)',
                'required' => false,
                'widget' => 'single_text',
                'help' => 'Permet de calculer l\'epargne mensuelle necessaire.',
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => BankGoal::class,
            'account_choices' => [],
        ]);
    }
}
