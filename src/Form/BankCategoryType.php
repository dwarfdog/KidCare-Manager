<?php

namespace App\Form;

use App\Entity\BankCategory;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\ColorType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\NotBlank;

final class BankCategoryType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('name', TextType::class, [
                'label' => 'Nom',
                'constraints' => [new NotBlank()],
            ])
            ->add('type', ChoiceType::class, [
                'label' => 'Type',
                'choices' => [
                    'Depense' => BankCategory::TYPE_EXPENSE,
                    'Revenu' => BankCategory::TYPE_INCOME,
                    'Virement' => BankCategory::TYPE_TRANSFER,
                    'Neutre' => BankCategory::TYPE_NEUTRAL,
                ],
            ])
            ->add('color', ColorType::class, [
                'label' => 'Couleur',
            ])
            ->add('icon', TextType::class, [
                'label' => 'Icone',
                'required' => false,
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults(['data_class' => BankCategory::class]);
    }
}
