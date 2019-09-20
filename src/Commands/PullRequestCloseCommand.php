<?php

namespace Pantheon\TerminusClu\Commands;

use Pantheon\TerminusBuildTools\Commands\BuildToolsBase;
use Pantheon\TerminusClu\ServiceProviders\RepositoryProviders\CluGitTrait;

/**
 * Class PullRequestCloseCommand
 *
 * @package Pantheon\TerminusClu\Commands
 */
class PullRequestCloseCommand extends BuildToolsBase {

  use CluGitTrait;

  /**
   * List pull requests.
   *
   * @authorize
   *
   * @command project:pull-request:close
   *
   * @aliases project:pr:close project:pr:close pr-close
   *
   * @param string $site_name_or_site_env_id Site name, or the site.env name.
   *
   * @option int $id If only site name is given, the id of the Pull Request to
   *   close. Otherwise, infer Pull Request id from environment branch.
   *
   * @usage <site> Lists open pull requests for <site>.
   *
   */
  public function closePullRequest($site_name_or_site_env_id, $id) {
    // Determine if we have a site name, or a site_env_id.
    if (strpos($site_name_or_site_env_id, '.')) {
      /** @var \Pantheon\Terminus\Models\Site $site */
      list($site, $env) = $this->getSiteEnv($site_name_or_site_env_id);
      $buildMetadata = $this->retrieveBuildMetadata($site_name_or_site_env_id);
    }
    else {
      /** @var \Pantheon\Terminus\Models\Site $site */
      $site = $this->getSite($site_name_or_site_env_id);
      $buildMetadata = $this->retrieveBuildMetadata("$site_name_or_site_env_id.dev");
    }

    $url = $this->getMetadataUrl($buildMetadata);
    $target_project = $this->projectFromRemoteUrl($url);

    if (!$this->confirm('Are you sure you want to close PR {id} for {project}?', [
      'id' => $id,
      'project' => $target_project,
    ])) {
      return;
    }

    // Create a Git repository service provider appropriate to the URL
    $this->inferGitProviderFromUrl($url);

    // Ensure that credentials for the Git provider are available
    $this->providerManager()->validateCredentials();

    $cluProvider = $this->inferGitCluProviderFromUrl($url);
    $cluProvider->setCredentials($this->providerManager()->credentialManager());
    $cluProvider->closePullRequest($target_project, $id);
  }

}
