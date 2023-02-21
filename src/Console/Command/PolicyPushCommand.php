<?php

namespace Drutiny\Console\Command;

use Drutiny\PolicyFactory;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Output\OutputInterface;
use Drutiny\PolicySource\PushablePolicySourceInterface;
use League\OAuth2\Client\Provider\Exception\IdentityProviderException;
use Psr\Log\LoggerInterface;

/**
 *
 */
class PolicyPushCommand extends DrutinyBaseCommand
{
    use LanguageCommandTrait;

    public function __construct(
      protected PolicyFactory $policyFactory,
      protected LoggerInterface $logger
    )
    {
      parent::__construct();
    }
  /**
   * @inheritdoc
   */
    protected function configure()
    {
        $this
        ->setName('policy:push')
        ->setDescription('Push a policy to a policy source.')
        ->addArgument(
          'policy',
          InputArgument::REQUIRED,
          'The name of the policy to push.'
        )
        ->addArgument(
            'source',
            InputArgument::REQUIRED,
            'The name of the source to push too.'
        );
    }

  /**
   * @inheritdoc
   */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $source = $this->policyFactory
          ->getSource($input->getArgument('source'))
          ->getDriver();

        $io = new SymfonyStyle($input, $output);

        if (!($source instanceof PushablePolicySourceInterface)) {
          $io->error('Source does not support policy push');
          return 1;
        }
        $policy = $this->policyFactory->loadPolicyByName($input->getArgument('policy'));

        try {
          $url = $source->push($policy);
        }
        catch (IdentityProviderException $e)
        {
          $this->logger->error(get_class($e));
          $this->logger->error($e->getMessage());
          return 2;
        }

        $io->success(sprintf('Policy %s successfully pushed to %s. Visit %s',
          $input->getArgument('policy'),
          $input->getArgument('source'),
          $url
        ));

        return 0;
    }
}
