<?php

namespace Pantheon\TerminusClu\Commands;

use Pantheon\Terminus\Exceptions\TerminusException;
use Pantheon\TerminusBuildTools\Commands\BuildToolsBase;
use Pantheon\TerminusClu\ServiceProviders\RepositoryProviders\CluGitTrait;

/**
 * Class PullRequestCreateCommand
 *
 * @package Pantheon\TerminusClu\Commands
 */
class PullRequestCreateCommand extends BuildToolsBase {

  use CluGitTrait;

  /**
   * Creates a new pull request.
   *
   * @authorize
   *
   * @command project:pull-request:create
   *
   * @aliases project:pr:create project:pr:create pr-create
   *
   * @param string $site_name_or_site_env_id The site whose pull requests to list.
   *   If site.env is given, use env as the source branch and ignore --source.
   *
   * @option string $source The source branch from which to create the PR. Either
   *   site.env or --source must be specified.
   *
   * @option string $target The target branch into which the PR will be merged.
   *   Defaults to repository.mainbranch on BitBucket and the default branch on GitHub and GitLab.
   *
   * @option string $title Short title for the pull request.
   *  Required
   *
   * @option string $description Extended description of the pull request.
   *   Defaults to null.
   *
   * @options string $reviewers Comma-separated list of UUIDs of reviewers to
   *   be assigned. Defaults to null. Available on BitBucket, not available on GitHub or GitLab.
   *
   * @options bool $close Whether to close the source branch upon merging.
   *   Defaults to FALSE. Available on BitBucket and GitLab, not available on GitHub.
   *
   * @usage <site>.<env> Creates a new pull request from <site>.<env>.
   *
   */
  public function createPullRequest($site_name_or_site_env_id, $options = [
    'source' => NULL,
    'target' => NULL,
    'title' => NULL,
    'description' => NULL,
  ]) {
    // Determine if we have a site name, or a site_env_id.
    if (strpos($site_name_or_site_env_id, '.')) {
      /** @var \Pantheon\Terminus\Models\Site $site */
      list($site, $env) = $this->getSiteEnv($site_name_or_site_env_id);
      $env_id = $env->getName();
      $source = ($env_id == 'dev') ? 'master' : $env_id;
      $buildMetadata = $this->retrieveBuildMetadata($site_name_or_site_env_id);
    }
    else {
      /** @var \Pantheon\Terminus\Models\Site $site */
      $site = $this->getSite($site_name_or_site_env_id);
      // Require source argument.
      // @TODO check that source branch is valid.
      if (empty($options['source'])) {
        throw new TerminusException('Either site and source branch, or site environment are required to create a pull request.');
      }
      $buildMetadata = $this->retrieveBuildMetadata("$site_name_or_site_env_id.dev");
      $source = $options['source'];
    }
    unset($options['source']);

    // @TODO check that target branch is valid.
    if (empty($options['target'])) {
      $options['target'] = 'master';
    }

    if ($source == $options['target']) {
      throw new TerminusException('Source and target branches must be different.');
    }

    $title = empty($options['title']) ? 'Pull request from Terminus.' : $options['title'];
    unset($options['title']);

    $url = $this->getMetadataUrl($buildMetadata);
    $target_project = $this->projectFromRemoteUrl($url);

    // Create a Git repository service provider appropriate to the URL
    $this->inferGitProviderFromUrl($url);

    // Ensure that credentials for the Git provider are available
    $this->providerManager()->validateCredentials();

    $options = array_filter($options);
    $cluProvider = $this->inferGitCluProviderFromUrl($url);
    $cluProvider->setCredentials($this->providerManager()->credentialManager());
    $cluProvider->createPullRequest($target_project, $source, $title, $options);
  }

}
