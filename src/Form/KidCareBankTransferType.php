<?php

namespace App\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

final class KidCareBankTransferType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('account', ChoiceType::class, ['label' => 'Compte bancaire', 'choices' => $options['account_choices']])
            ->add('category', ChoiceType::class, ['label' => 'Categorie', 'choices' => $options['category_choices'], 'required' => false])
            ->add('date', DateType::class, ['label' => 'Date de paiement', 'widget' => 'single_text'])
            ->add('label', TextType::class, ['label' => 'Libelle', 'required' => false]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => null,
            'account_choices' => [],
            'category_choices' => [],
        ]);
    }
}
