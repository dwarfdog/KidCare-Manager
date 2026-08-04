<?php

namespace App\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\NotBlank;

/**
 * Profil de mapping reutilisable pour les exports CSV/XLSX structures a colonnes fixes (App\Entity\CourseImportProfile),
 * meme esprit que App\Form\BankImportType pour l'import bancaire. Les champs mappingX correspondent aux
 * noms de colonnes (en-tetes) du fichier source.
 */
final class CourseImportProfileType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('name', TextType::class, [
                'label' => 'Nom du profil',
                'help' => 'Ex : Export Carrefour Drive',
                'constraints' => [new NotBlank(['message' => 'Le nom du profil est obligatoire.'])],
            ])
            ->add('delimiter', TextType::class, [
                'label' => 'Delimiteur CSV',
                'required' => false,
                'help' => 'Laisser vide pour une detection automatique (; , ou tabulation).',
            ])
            ->add('mappingLabel', TextType::class, [
                'label' => 'Colonne : libelle du produit',
                'constraints' => [new NotBlank(['message' => 'La colonne libelle est obligatoire.'])],
            ])
            ->add('mappingQuantity', TextType::class, [
                'label' => 'Colonne : quantite',
                'required' => false,
            ])
            ->add('mappingUnit', TextType::class, [
                'label' => 'Colonne : unite (kg/g/l/ml)',
                'required' => false,
            ])
            ->add('mappingUnitPrice', TextType::class, [
                'label' => 'Colonne : prix unitaire',
                'required' => false,
            ])
            ->add('mappingTotal', TextType::class, [
                'label' => 'Colonne : montant de la ligne',
                'constraints' => [new NotBlank(['message' => 'La colonne montant est obligatoire.'])],
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults(['data_class' => null]);
    }
}
