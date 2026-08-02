<?php

namespace App\Form;

use App\Entity\BankAccount;
use App\Entity\BankTransaction;
use App\Service\BankTransactionManager;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\Extension\Core\Type\NumberType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\GreaterThan;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\Callback;
use Symfony\Component\Validator\Context\ExecutionContextInterface;

final class BankTransactionType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $typeChoices = BankTransactionManager::TYPES;
        if (!$options['allow_internal_transfer']) {
            unset($typeChoices[BankTransactionManager::TYPE_INTERNAL_TRANSFER]);
        }

        $builder
            ->add('account', ChoiceType::class, [
                'label' => 'Compte source',
                'choices' => $options['account_choices'],
            ])
            ->add('targetAccount', ChoiceType::class, [
                'label' => 'Compte destination',
                'choices' => $options['account_choices'],
                'required' => false,
            ])
            ->add('category', ChoiceType::class, [
                'label' => 'Categorie',
                'choices' => $options['category_choices'],
                'required' => false,
            ])
            ->add('type', ChoiceType::class, [
                'label' => 'Type',
                'choices' => array_flip($typeChoices),
            ])
            ->add('date', DateType::class, [
                'label' => 'Date',
                'widget' => 'single_text',
            ])
            ->add('label', TextType::class, [
                'label' => 'Libelle',
                'constraints' => [new NotBlank()],
            ])
            ->add('amount', NumberType::class, [
                'label' => 'Montant',
                'scale' => 2,
                'constraints' => [new GreaterThan(0)],
            ])
            ->add('status', ChoiceType::class, [
                'label' => 'Statut',
                'choices' => array_flip(BankTransactionManager::STATUSES),
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => null,
            'account_choices' => [],
            'category_choices' => [],
            'allow_internal_transfer' => false,
            'constraints' => [new Callback([self::class, 'validateOperation'])],
        ]);
    }

    public static function validateOperation(mixed $data, ExecutionContextInterface $context): void
    {
        if (!is_array($data) || ($data['type'] ?? null) !== BankTransactionManager::TYPE_INTERNAL_TRANSFER) {
            return;
        }

        $source = $data['account'] ?? null;
        $target = $data['targetAccount'] ?? null;
        if (!$target instanceof BankAccount) {
            $context->buildViolation('Le compte destination est obligatoire pour un virement interne.')
                ->atPath('[targetAccount]')
                ->addViolation();

            return;
        }

        if ($source instanceof BankAccount && $source->getPublicId() === $target->getPublicId()) {
            $context->buildViolation('Les comptes source et destination doivent etre differents.')
                ->atPath('[targetAccount]')
                ->addViolation();
        }
    }
}
