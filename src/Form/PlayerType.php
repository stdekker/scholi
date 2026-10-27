<?php

declare(strict_types=1);

namespace App\Form;

use App\Entity\Player;
use App\Player\FunNameGenerator;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Validator\Constraints\Choice;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\NotBlank;

/**
 * @extends AbstractType<array{name: string, emoji: string}>
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

        // Radios rendered in a popup grid (templates/player/new.html.twig); label = emoji.
        $builder->add('emoji', ChoiceType::class, [
            'label' => 'player.new.emoji_label',
            'choices' => FunNameGenerator::emojis(),
            'choice_label' => static fn (string $emoji): string => $emoji,
            'choice_translation_domain' => false,
            'choice_attr' => static fn (string $emoji, string $animal): array => ['aria-label' => $animal, 'title' => $animal],
            'expanded' => true,
            'constraints' => [
                new NotBlank(),
                new Choice(choices: array_values(FunNameGenerator::emojis())),
            ],
        ]);
    }
}
