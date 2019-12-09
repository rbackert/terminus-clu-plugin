<?php

namespace Pantheon\TerminusClu\Commands;

use Pantheon\Terminus\Exceptions\TerminusException;
use Pantheon\TerminusBuildTools\Commands\BuildToolsBase;
use Pantheon\TerminusClu\ServiceProviders\RepositoryProviders\CluGitTrait;

class ComposerLockUpdateCommand extends BuildToolsBase {

  use CluGitTrait;

  /**
   * @var string
   */
  protected $target_project;

  /**
   * @var string
   */
  protected $working_dir;

  /**
   * @var \Pantheon\TerminusClu\ServiceProviders\RepositoryProviders\GitProvider
   */
  protected $cluProvider;

  /**
   * Check for composer dependency updates and create a PR if applicable.
   *
   * @authorize
   *
   * @command project:clu
   *
   * @aliases pr-clu clu
   *
   * @param string $site_name Site name.
   *
   * @option bool $security-only If given, check only for security updates.
   *
   * @usage <site> Create a composer.lock pull request if applicable.
   *
   */
  public function composerLockUpdate($site_name, $options = ['security-only' => FALSE]) {
    /** @var \Pantheon\Terminus\Models\Site $site */
    $site = $this->getSite($site_name);
    $buildMetadata = $this->retrieveBuildMetadata("$site_name.dev");
    $url = $this->getMetadataUrl($buildMetadata);
    $this->target_project = $this->projectFromRemoteUrl($url);

    // Create a Git repository service provider appropriate to the URL
    $this->inferGitProviderFromUrl($url);

    // Ensure that credentials for the Git provider are available
    $this->providerManager()->validateCredentials();

    $this->cluProvider = $this->inferGitCluProviderFromUrl($url);
    $this->cluProvider->setCredentials($this->providerManager()->credentialManager());

    // Create a working directory
    $this->working_dir = $this->tempdir('local-site');

    $this->cluProvider->cloneRepository($this->target_project, $this->working_dir);

    // Check if there are any security advisories for any of the
    // versions of any of our dependencies in use right now.
    $security_message = $this->checkSensiolabsSecurity($this->working_dir . '/composer.lock', $is_vulnerable);
    $this->log()->notice($security_message);

    // Exit early if user requested security updates only, and no dependencies
    // are vulnerable.
    if (isset($options['security-only']) && !$is_vulnerable) {
      $this->log()->notice('There are no security updates available.');
      return;
    }

    // Determine whether there is an existing open PR with Composer updates
    $existing_PR_branch = $this->checkExistingPRBranch('branch');
    if ($existing_PR_branch) {
      $initial_branch = reset( $this->exec("git -C {$this->working_dir} rev-parse --abbrev-ref HEAD") );
      $this->passthru("git -C {$this->working_dir} fetch");
      $this->passthru("git -C {$this->working_dir} checkout $existing_PR_branch");

      $this->runComposerInstall();
      $this->runComposerUpdate();
      if (!$this->checkComposerLock()) {
        return;
      }
      // Close the existing PR and delete its branch.
      $this->closeExistingPRBranch($existing_PR_branch);

      // Check out the initial branch locally and delete the local PR branch.
      $this->passthru("git -C {$this->working_dir} checkout -f $initial_branch");
      $this->passthru("git -C {$this->workind_dir} branch -D $existing_PR_branch");
    }

    // Perform an initial install to sanity check the package.
    $this->runComposerInstall();

    // Run composer update, but capture output for the commit message if needed.
    $update_message = implode(PHP_EOL, $this->runComposerUpdate());

    // Check whether composer.lock was modifed.
    if ( ! $this->checkComposerLock() ) {
      return;
    }

    $date = date( 'Y-m-d-H-i' );
    // Checkout a dated branch to make the commit
    $branch_name = 'clu-' . $date;
    $this->passthru("git -C {$this->working_dir} checkout -b $branch_name");

    $title = "Update Composer dependencies ({$date})";
    $description = <<<EOT
```
{$update_message}{$security_message}
```
EOT;
    $message = $title . PHP_EOL . $description;
    $this->passthru("git -C {$this->working_dir} commit -am " . escapeshellarg($message));
    $this->passthru("git -C {$this->working_dir} push origin $branch_name");
    $this->cluProvider->createPullRequest($this->target_project, $branch_name, $title, ['description' => $description]);
  }

  /**
   * Check the Sensiolabs security component if availble.
   *
   * $security_status will be 0 if the code is believed to be good, and
   * will be non-zero if vulnerabilities were detected (status == 1), or
   * if the vulnerability status is unknown (status == 127).
   */
  protected function checkSensiolabsSecurity($composerLockPath, &$security_status) {
    // If the security-checker app is not installed, return an empty message
    exec('which security-checker.phar', $outputOfWhich, $return_code);
    if ($return_code) {
      $security_status = 127;
      return '';
    }

    exec('security-checker.phar security:check ' . $composerLockPath, $output, $is_vulnerable);
    return "\n\n" . implode("\n", $output);
  }

  /**
   * Checks to see if there's an existing PR branch.
   *
   * @param string $type Type of value to return.
   *
   * @return string|int|void
   */
  protected function checkExistingPRBranch($type) {
    $prs = $this->git_provider->branchesForPullRequests($this->target_project, 'open', null, 'branch');
    foreach ($prs as $id => $branch) {
      if (preg_match('/^clu-[0-9-]+/', $branch)) {
        $this->logger
        ->notice("Found existing CLU branch: {branch}", [
          "branch" => $branch
        ]);
        return $type == 'branch' ? $branch : $id;
      }
    }
  }

  /**
   * Runs `composer install`.
   */
  protected function runComposerInstall() {
    $args = getenv('CLU_COMPOSER_INSTALL_ARGS') ?: '--no-dev --no-interaction';
    return $this->passthru("composer install --working-dir={$this->working_dir} $args");
  }

  /**
   * Runs `composer update`.
   */
  protected function runComposerUpdate() {
    $args = getenv('CLU_COMPOSER_UPDATE_ARGS') ?: '--no-progress --no-dev --no-interaction';
    return $this->exec("composer update --working-dir={$this->working_dir} $args 2>&1 | tee {$this->working_dir}/vendor/update.log");
  }

  /**
   * Checks a `composer.lock` file to see if changes were detected.
   *
   * @return boolean
   */
  protected function checkComposerLock() {
    $output = $this->exec("git -C {$this->working_dir} status -s composer.lock");
    if (empty($output)) {
      $this->log()->notice('No changes detected to composer.lock');
      return FALSE;
    }
    $this->log()->notice('Detected changes to composer.lock');
    return TRUE;
  }

  /**
   * Closes an existing PR branch.
   *
   * @param string $branch_name Name of the branch.
   * @return boolean
   */
  protected function closeExistingPRBranch( $branch_name ) {
    $number = $this->checkExistingPRBranch( 'number' );
    if ( ! $number ) {
      throw new TerminusException('Unable to find existing PR for {branch}', ['branch' => $branch_name]);
    }
    if (!$this->cluProvider->closePullRequest($this->target_project, $number)) {
      throw new TerminusException('Failed to close existing composer lock update PR');
    }
    $this->exec("git -C {$this->working_dir} push origin --delete $branch_name");
    $this->log()->info("Closed existing PR {number} and branch {branch}", ['number' => $number, 'branch' => $branch_name]);
  }

}