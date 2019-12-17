<?php

namespace Pantheon\TerminusClu\Commands;

class ComposerLockUpdateGitCommand extends ComposerLockUpdateCommand {
	/**
	 * Check for composer dependency updates and create a PR if applicable, based on a Git repository url.
	 *
	 * @command project:clu:git
	 *
	 * @aliases pr-clu-git clu-git
	 *
	 * @param string $git_url Git repository url.
	 *
	 * @option bool $security-only If given, check only for security updates.
	 *
	 * @usage <url> Create a composer.lock pull request if applicable.
	 */
	public function composerLockUpdate( $git_url, $options = array( 'security-only' => false ) ) {
		$this->target_project = $this->projectFromRemoteUrl( $git_url );

		// Create a Git repository service provider appropriate to the URL
		$this->inferGitProviderFromUrl( $git_url );

		// Ensure that credentials for the Git provider are available
		$this->providerManager()->validateCredentials();

		$this->cluProvider = $this->inferGitCluProviderFromUrl( $git_url );
		$this->cluProvider->setCredentials( $this->providerManager()->credentialManager() );

		// Create a working directory
		$this->working_dir = $this->tempdir( 'local-site' );

		$this->cluProvider->cloneRepository( $this->target_project, $this->working_dir );

		// Check if there are any security advisories for any of the
		// versions of any of our dependencies in use right now.
		$security_message = $this->checkSensiolabsSecurity( $this->working_dir . '/composer.lock', $is_vulnerable );
		$this->log()->notice( $security_message );

		// Exit early if user requested security updates only, and no dependencies
		// are vulnerable.
		if ( isset( $options['security-only'] ) && ! $is_vulnerable ) {
			$this->log()->notice( 'There are no security updates available.' );
			return;
		}

		// Determine whether there is an existing open PR with Composer updates
		$existing_PR_branch = $this->checkExistingPRBranch( 'branch' );
		if ( $existing_PR_branch ) {
			$initial_branch = reset( $this->exec( "git -C {$this->working_dir} rev-parse --abbrev-ref HEAD" ) );
			$this->passthru( "git -C {$this->working_dir} fetch" );
			$this->passthru( "git -C {$this->working_dir} checkout $existing_PR_branch" );

			$this->runComposerInstall();
			$this->runComposerUpdate();
			if ( ! $this->checkComposerLock() ) {
				return;
			}
			// Close the existing PR and delete its branch.
			$this->closeExistingPRBranch( $existing_PR_branch );

			// Check out the initial branch locally and delete the local PR branch.
			$this->passthru( "git -C {$this->working_dir} checkout -f $initial_branch" );
			$this->passthru( "git -C {$this->workind_dir} branch -D $existing_PR_branch" );
		}

		// Perform an initial install to sanity check the package.
		$this->runComposerInstall();

		// Run composer update, but capture output for the commit message if needed.
		$update_message = implode( PHP_EOL, $this->runComposerUpdate() );

		// Check whether composer.lock was modifed.
		if ( ! $this->checkComposerLock() ) {
			return;
		}

		$date = date( 'Y-m-d-H-i' );
		// Checkout a dated branch to make the commit
		$branch_name = 'clu-' . $date;
		$this->passthru( "git -C {$this->working_dir} checkout -b $branch_name" );

		$title       = "Update Composer dependencies ({$date})";
		$description = <<<EOT
```
{$update_message}{$security_message}
```
EOT;
		$message     = $title . PHP_EOL . $description;
		$this->passthru( "git -C {$this->working_dir} commit -am " . escapeshellarg( $message ) );
		$this->passthru( "git -C {$this->working_dir} push origin $branch_name" );
		$this->cluProvider->createPullRequest( $this->target_project, $branch_name, $title, array( 'description' => $description ) );
	}
}
