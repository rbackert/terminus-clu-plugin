<?php

namespace Pantheon\TerminusClu\ServiceProviders\RepositoryProviders;

use Pantheon\TerminusBuildTools\ServiceProviders\RepositoryProviders\GitProvider as BuildToolsGitProvider;

/**
 * Wraps Pantheon's Git Provider interface to expose PR methods for CLU.
 */
interface GitProvider extends BuildToolsGitProvider {
  public function closePullRequest($target_project, $id);
  public function createPullRequest($target_project, $source_branch, $title, array $options = []);
  public function cloneRepository($target_project, $destination);
}