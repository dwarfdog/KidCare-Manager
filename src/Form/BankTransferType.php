<?php

namespace App\Form;

use App\Entity\BankAccount;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\Extension\Core\Type\NumberType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\Callback;
use Symfony\Component\Validator\Constraints\GreaterThan;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Context\ExecutionContextInterface;

final class BankTransferType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('source', ChoiceType::class, ['label' => 'Compte source', 'choices' => $options['account_choices']])
            ->add('target', ChoiceType::class, ['label' => 'Compte destination', 'choices' => $options['account_choices']])
            ->add('date', DateType::class, ['label' => 'Date', 'widget' => 'single_text'])
            ->add('label', TextType::class, ['label' => 'Libelle', 'constraints' => [new NotBlank()]])
            ->add('amount', NumberType::class, ['label' => 'Montant', 'scale' => 2, 'constraints' => [new GreaterThan(0)]]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => null,
            'account_choices' => [],
            'constraints' => [new Callback([self::class, 'validateDifferentAccounts'])],
        ]);
    }

    public static function validateDifferentAccounts(mixed $data, ExecutionContextInterface $context): void
    {
        if (!is_array($data)) {
            return;
        }

        $source = $data['source'] ?? null;
        $target = $data['target'] ?? null;
        if (!$source instanceof BankAccount || !$target instanceof BankAccount) {
            return;
        }

        if ($source->getPublicId() === $target->getPublicId()) {
            $context->buildViolation('Les comptes source et cible doivent etre differents.')
                ->atPath('[target]')
                ->addViolation();
        }
    }
}
