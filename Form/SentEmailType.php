<?php

namespace  Azine\EmailBundle\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;

class SentEmailType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder->setMethod('GET');
        $builder->add('recipients', TextType::class, array('label' => false, 'required' => false));
        $builder->add('template', TextType::class, array('label' => false, 'required' => false));
        $builder->add('sent', TextType::class, array('label' => false, 'required' => false));
        $builder->add('variables', TextType::class, array('label' => false, 'required' => false));
        $builder->add('token', TextType::class, array('label' => false, 'required' => false));

        $builder->add('filter', SubmitType::class, array('label' => 'email.dashboard.filter.button.label', 'attr' => array('class' => 'button')));
    }

    /**
     * @return string
     */
    public function getBlockPrefix()
    {
        return 'sentEmail';
    }
}
