<?php

declare(strict_types=1);

namespace Wunderio\GrumPHP\Task\PhpCheckSyntax;

use GrumPHP\Collection\FilesCollection;
use GrumPHP\Runner\TaskResult;
use GrumPHP\Runner\TaskResultInterface;
use GrumPHP\Task\Context\ContextInterface;
use GrumPHP\Task\Context\GitPreCommitContext;
use GrumPHP\Task\Context\RunContext;
use Symfony\Component\Finder\Finder;
use Symfony\Component\OptionsResolver\OptionsResolver;
use GrumPHP\Task\AbstractExternalTask;
use Wunderio\GrumPHP\Task\ContextFileExternalTaskBase;

/**
 * Class PhpCheckSyntaxTask.
 *
 * @package Wunderio\GrumPHP\Task\PhpCheckSyntaxTask
 */
class PhpCheckSyntaxTask extends ContextFileExternalTaskBase
{
  /**
   * {@inheritdoc}
   */
  public function getName(): string
  {
    return 'php_check_syntax';
  }

  /**
   * {@inheritdoc}
   */
  public function getConfigurableOptions(): OptionsResolver
  {
    $resolver = new OptionsResolver();
    $resolver->setDefaults([
      'ignore_patterns' => ['/vendor/','/node_modules/', '/core/', 'modules/contrib'],
      'extensions' => ['php', 'inc', 'install', 'module'],
      'run_on' => ['.']
    ]);
    $resolver->addAllowedTypes('ignore_patterns', ['array']);
    $resolver->setAllowedTypes('extensions', 'array');
    $resolver->setAllowedTypes('run_on', ['array']);
    return $resolver;
  }

  /**
   * {@inheritdoc}
   */
  public function canRunInContext(ContextInterface $context): bool
  {
    return $context instanceof GitPreCommitContext || $context instanceof RunContext;
  }

  /**
   * {@inheritdoc}
   */
  public function run(ContextInterface $context): TaskResultInterface
  {
    $config = $this->getConfiguration();
    $files = $this->getFiles($context, $config);
    if ($files->isEmpty()) {
      return TaskResult::createSkipped($this, $context);
    }

    if (!$context instanceof GitPreCommitContext) {
      $files = Finder::create()->files();
      foreach ($config['extensions'] as $extension) {
        $files->name('*.' . $extension);
      }
      foreach ($config['run_on'] as $run_on) {
        $files->in($run_on);
      }
      foreach ($config['ignore_patterns'] as $ignore_patterns) {
        $files->notPath($ignore_patterns);
      }
      $files_array = [];
      foreach ($files as $file) {
        $files_array[] = $file;
      }

      $files = new FilesCollection($files_array);
    }

    $output = '';
    foreach ($files as $file) {
      $arguments = $this->processBuilder->createArgumentsForCommand('php');
      $arguments->add('-l');
      $arguments->add($file);
      $process = $this->processBuilder->buildProcess($arguments);
      $process->run();

      if (!$process->isSuccessful()) {
        $output .= PHP_EOL . $this->formatter->format($process);
      }
    }


    if ($output !== '') {
      return TaskResult::createFailed($this, $context, $output);
    }

    return TaskResult::createPassed($this, $context);
  }

}
