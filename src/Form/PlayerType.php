<?php

declare(strict_types=1);

namespace App\Form;

use App\Entity\Player;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\NotBlank;

/**
 * @extends AbstractType<array{name: string}>
 */
final class PlayerType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder->add('name', TextType::class, [
            'label' => 'player.new.name_label',
            'help' => 'player.new.name_help',
            'attr' => [
                'maxlength' => Player::NAME_MAX_LENGTH,
                'autocomplete' => 'off',
            ],
            'constraints' => [
                new NotBlank(),
                new Length(max: Player::NAME_MAX_LENGTH),
            ],
        ]);
    }
}
