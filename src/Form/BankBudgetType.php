<?php

namespace App\Form;

use App\Entity\BankBudget;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\Extension\Core\Type\NumberType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\Callback;
use Symfony\Component\Validator\Constraints\GreaterThan;
use Symfony\Component\Validator\Context\ExecutionContextInterface;

final class BankBudgetType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('category', ChoiceType::class, [
                'label' => 'Categorie',
                'choices' => $options['category_choices'],
                'help' => 'Les depenses de cette categorie seront comparees au plafond.',
            ])
            ->add('periodType', ChoiceType::class, [
                'label' => 'Periode',
                'choices' => [
                    'Mensuelle (reconduite chaque mois)' => BankBudget::PERIOD_MONTH,
                    'Hebdomadaire (reconduite chaque semaine)' => BankBudget::PERIOD_WEEK,
                    'Annuelle (reconduite chaque annee)' => BankBudget::PERIOD_YEAR,
                    'Personnalisee (dates fixes, sans reconduction)' => BankBudget::PERIOD_CUSTOM,
                ],
            ])
            ->add('periodStart', DateType::class, ['label' => 'Debut', 'widget' => 'single_text'])
            ->add('periodEnd', DateType::class, ['label' => 'Fin', 'widget' => 'single_text'])
            ->add('amount', NumberType::class, [
                'label' => 'Plafond de depenses (€)',
                'scale' => 2,
                'constraints' => [new GreaterThan(0)],
                'help' => 'Montant maximum a ne pas depasser sur la periode.',
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => BankBudget::class,
            'category_choices' => [],
            'constraints' => [new Callback([self::class, 'validateBudgetPeriod'])],
        ]);
    }

    public static function validateBudgetPeriod(mixed $data, ExecutionContextInterface $context): void
    {
        if (!$data instanceof BankBudget) {
            return;
        }

        if ($data->getPeriodEnd() < $data->getPeriodStart()) {
            $context->buildViolation('La date de fin doit etre posterieure ou egale a la date de debut.')
                ->atPath('periodEnd')
                ->addViolation();
        }
    }
}
