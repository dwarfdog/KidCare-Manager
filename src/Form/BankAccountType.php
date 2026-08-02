<?php

namespace App\Form;

use App\Service\BankAccountManager;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\NumberType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\GreaterThanOrEqual;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\Range;

final class BankAccountType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('name', TextType::class, [
                'label' => 'Nom du compte',
                'constraints' => [new NotBlank(['message' => 'Le nom du compte est obligatoire.'])],
            ])
            ->add('type', ChoiceType::class, [
                'label' => 'Type',
                'choices' => array_flip(BankAccountManager::TYPES),
            ])
            ->add('initialBalance', NumberType::class, [
                'label' => 'Solde initial',
                'scale' => 2,
                'html5' => true,
            ])
            ->add('initialBalanceDate', DateType::class, [
                'label' => 'Date du solde initial',
                'widget' => 'single_text',
            ])
            ->add('iban', TextType::class, [
                'label' => 'IBAN',
                'required' => false,
            ])
            ->add('loanInitialCapital', NumberType::class, [
                'label' => 'Capital initial du pret',
                'required' => false,
                'scale' => 2,
                'constraints' => [new GreaterThanOrEqual(0)],
            ])
            ->add('loanRemainingCapital', NumberType::class, [
                'label' => 'Capital restant du',
                'required' => false,
                'scale' => 2,
                'constraints' => [new GreaterThanOrEqual(0)],
            ])
            ->add('loanMonthlyPayment', NumberType::class, [
                'label' => 'Mensualite',
                'required' => false,
                'scale' => 2,
                'constraints' => [new GreaterThanOrEqual(0)],
                'help' => 'Montant preleve chaque mois (assurance incluse si applicable).',
            ])
            ->add('loanPaymentDay', IntegerType::class, [
                'label' => 'Jour de prelevement',
                'required' => false,
                'constraints' => [new Range(min: 1, max: 31)],
                'attr' => ['min' => 1, 'max' => 31],
                'help' => 'Jour du mois (1 a 31) ou la mensualite est prelevee.',
            ])
            ->add('loanDebitAccount', ChoiceType::class, [
                'label' => 'Compte a debiter',
                'required' => false,
                'placeholder' => '— Choisir un compte courant —',
                'choices' => $options['debit_account_choices'],
                'help' => 'La mensualite sera enregistree comme depense sur ce compte.',
            ])
            ->add('loanCategory', ChoiceType::class, [
                'label' => 'Categorie des echeances',
                'required' => false,
                'placeholder' => '— Sans categorie —',
                'choices' => $options['loan_category_choices'],
                'help' => 'Categorie appliquee aux echeances du pret (passees et futures), visible dans les depenses par categorie.',
            ])
            ->add('loanAnnualRate', NumberType::class, [
                'label' => 'Taux annuel indicatif (%)',
                'required' => false,
                'scale' => 3,
                'constraints' => [new GreaterThanOrEqual(0)],
            ])
            ->add('loanEndDate', DateType::class, [
                'label' => 'Date de fin (optionnel)',
                'required' => false,
                'widget' => 'single_text',
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => null,
            'debit_account_choices' => [],
            'loan_category_choices' => [],
        ]);
    }
}
