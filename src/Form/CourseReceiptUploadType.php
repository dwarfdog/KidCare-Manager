<?php

namespace App\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\File;

final class CourseReceiptUploadType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('file', FileType::class, [
                'label' => 'Ticket ou facture',
                'help' => 'Photo (JPEG/PNG/WEBP), PDF, TXT, DOCX, CSV ou XLSX - 20 Mo maximum.',
                'constraints' => [new File([
                    'maxSize' => '20M',
                    'mimeTypes' => [
                        'application/pdf',
                        'image/jpeg', 'image/png', 'image/webp', 'image/gif', 'image/bmp', 'image/tiff',
                        'text/plain', 'text/csv',
                        'application/vnd.ms-excel',
                        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                        'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
                    ],
                    'mimeTypesMessage' => 'Format non supporte. Formats acceptes : photo, PDF, TXT, DOCX, CSV, XLSX.',
                ])],
            ])
            ->add('account', ChoiceType::class, [
                'label' => 'Compte qui a paye',
                'placeholder' => 'A choisir lors de la relecture',
                'required' => false,
                'choices' => $options['account_choices'],
            ])
            ->add('importProfile', ChoiceType::class, [
                'label' => 'Profil d\'import (CSV/XLSX)',
                'help' => 'Uniquement utilise si le fichier est un CSV/XLSX a colonnes fixes connues.',
                'placeholder' => 'Aucun (analyse en texte libre)',
                'required' => false,
                'choices' => $options['profile_choices'],
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => null,
            'account_choices' => [],
            'profile_choices' => [],
        ]);
    }
}
