<?php

namespace Pantheon\TerminusClu\Commands;

use Consolidation\OutputFormatters\StructuredData\RowsOfFields;
use Pantheon\Terminus\Exceptions\TerminusException;
use Pantheon\TerminusBuildTools\Commands\BuildToolsBase;

/**
 * Class PullRequestListCommand
 *
 * @package Pantheon\TerminusClu\Commands
 */
class PullRequestListCommand extends BuildToolsBase {

  /**
   * List pull requests.
   *
   * @authorize
   *
   * @command project:pull-request:list
   *
   * @aliases project:pr:list project:pr:list pr-list
   *
   * @field-labels
   *     id: Pull Request ID
   *     source: Source Branch
   * @default-fields id,source
   * @return RowsOfFields
   *
   * @param string $site_name_or_site_env_id The site or site.env whose pull
   *   requests to list.
   *
   * @option string $state [open|closed|all] Return
   *   PRs of only the given state. Ignored if $id is given.
   *
   * @option int $id Return info for only the given PR number.
   *
   * @usage <site> Lists open pull requests for <site>.
   *
   */
  public function listPullRequests($site_name_or_site_env_id, $options = ['state' => 'all', 'id' => NULL]) {
    $state = $options['state'];
    $stateParameters = ['open', 'closed', 'all'];
    if (!in_array($state, $stateParameters)) {
      throw new TerminusException("branchesForPullRequests - state must be one of: open, closed, all");
    }

    $site_name_or_site_env_id .= strpos($site_name_or_site_env_id, '.') ? '' : ".dev";
    $buildMetadata = $this->retrieveBuildMetadata($site_name_or_site_env_id);

    $url = $this->getMetadataUrl($buildMetadata);
    $target_project = $this->projectFromRemoteUrl($url);

    // Create a Git repository service provider appropriate to the URL
    $this->inferGitProviderFromUrl($url);

    // Ensure that credentials for the Git provider are available
    $this->providerManager()->validateCredentials();

    return $this->rowsOfFieldsFromPullRequestData($this->git_provider->branchesForPullRequests($target_project, $state), null, 'branch');
  }

  protected function rowsOfFieldsFromPullRequestData($data) {
    $dataFix = [];
    foreach ($data as $id => $branch) {
      $item = [
        'id' => $id,
        'source' => $branch,
      ];
      $dataFix[] = $item;
    }
    return new RowsOfFields($dataFix);
  }

}
