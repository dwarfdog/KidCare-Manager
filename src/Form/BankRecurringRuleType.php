<?php

namespace App\Form;

use App\Entity\BankRecurringRule;
use App\Entity\BankTransaction;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\NumberType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\Callback;
use Symfony\Component\Validator\Constraints\GreaterThan;
use Symfony\Component\Validator\Constraints\GreaterThanOrEqual;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Context\ExecutionContextInterface;

final class BankRecurringRuleType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('account', ChoiceType::class, ['label' => 'Compte', 'choices' => $options['account_choices']])
            ->add('category', ChoiceType::class, ['label' => 'Categorie', 'choices' => $options['category_choices'], 'required' => false])
            ->add('type', ChoiceType::class, [
                'label' => 'Type',
                'choices' => [
                    'Depense' => BankTransaction::TYPE_EXPENSE,
                    'Revenu' => BankTransaction::TYPE_INCOME,
                ],
            ])
            ->add('label', TextType::class, ['label' => 'Libelle', 'constraints' => [new NotBlank()]])
            ->add('amount', NumberType::class, ['label' => 'Montant', 'scale' => 2, 'constraints' => [new GreaterThan(0)]])
            ->add('frequency', ChoiceType::class, [
                'label' => 'Frequence',
                'choices' => [
                    'Hebdomadaire' => BankRecurringRule::FREQUENCY_WEEKLY,
                    'Mensuelle' => BankRecurringRule::FREQUENCY_MONTHLY,
                    'Annuelle' => BankRecurringRule::FREQUENCY_YEARLY,
                    'Tous les X jours' => BankRecurringRule::FREQUENCY_CUSTOM_DAYS,
                ],
            ])
            ->add('intervalDays', IntegerType::class, [
                'label' => 'Intervalle en jours',
                'required' => false,
                'constraints' => [new GreaterThanOrEqual(1)],
            ])
            ->add('startDate', DateType::class, ['label' => 'Debut', 'widget' => 'single_text'])
            ->add('endDate', DateType::class, ['label' => 'Fin (optionnel)', 'required' => false, 'widget' => 'single_text'])
            ->add('isSalaryReference', CheckboxType::class, [
                'label' => 'Utiliser comme repere de salaire',
                'required' => false,
                'help' => 'La prochaine occurrence marque le debut de la periode suivante (borne du reste a vivre et des previsionnels).',
            ])
            ->add('periodAttachment', ChoiceType::class, [
                'label' => 'Rattachement au previsionnel (revenus)',
                'choices' => [
                    'Comptee dans la periode ou elle tombe' => BankRecurringRule::ATTACHMENT_CURRENT,
                    'Reservee a la periode suivante' => BankRecurringRule::ATTACHMENT_NEXT,
                ],
                'help' => 'Pour un revenu qui arrive juste avant le salaire mais destine au budget suivant: il sera exclu du previsionnel de la periode en cours. Ignore pour les depenses.',
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => null,
            'account_choices' => [],
            'category_choices' => [],
            'constraints' => [new Callback([self::class, 'validateRule'])],
        ]);
    }

    public static function validateRule(mixed $data, ExecutionContextInterface $context): void
    {
        if (!is_array($data)) {
            return;
        }

        $frequency = $data['frequency'] ?? null;
        $intervalDays = $data['intervalDays'] ?? null;
        if ($frequency === BankRecurringRule::FREQUENCY_CUSTOM_DAYS && ($intervalDays === null || (int) $intervalDays < 1)) {
            $context->buildViolation('L\'intervalle est obligatoire pour une recurrence tous les X jours.')
                ->atPath('[intervalDays]')
                ->addViolation();
        }

        $startDate = $data['startDate'] ?? null;
        $endDate = $data['endDate'] ?? null;
        if ($startDate instanceof \DateTimeInterface && $endDate instanceof \DateTimeInterface && $endDate < $startDate) {
            $context->buildViolation('La date de fin doit etre posterieure ou egale a la date de debut.')
                ->atPath('[endDate]')
                ->addViolation();
        }
    }
}
