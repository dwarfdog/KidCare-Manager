<?php

namespace App\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\File;
use Symfony\Component\Validator\Constraints\NotBlank;

final class BankImportType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('account', ChoiceType::class, ['label' => 'Compte', 'choices' => $options['account_choices']])
            ->add('file', FileType::class, [
                'label' => 'Fichier CSV',
                'constraints' => [new File(['maxSize' => '10M', 'mimeTypes' => ['text/plain', 'text/csv', 'application/vnd.ms-excel']])],
            ])
            ->add('delimiter', TextType::class, ['label' => 'Separateur', 'data' => ';'])
            ->add('dateFormat', TextType::class, ['label' => 'Format de date', 'data' => 'd/m/Y'])
            ->add('dateColumn', TextType::class, ['label' => 'Colonne date', 'constraints' => [new NotBlank()]])
            ->add('labelColumn', TextType::class, ['label' => 'Colonne libelle', 'constraints' => [new NotBlank()]])
            ->add('amountColumn', TextType::class, ['label' => 'Colonne montant unique', 'required' => false])
            ->add('debitColumn', TextType::class, ['label' => 'Colonne debit', 'required' => false])
            ->add('creditColumn', TextType::class, ['label' => 'Colonne credit', 'required' => false])
            ->add('saveProfile', CheckboxType::class, ['label' => 'Sauvegarder ce mapping', 'required' => false])
            ->add('profileName', TextType::class, ['label' => 'Nom du profil', 'required' => false]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults(['data_class' => null, 'account_choices' => []]);
    }
}
