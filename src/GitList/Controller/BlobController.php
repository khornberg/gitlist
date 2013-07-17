<?php

namespace GitList\Controller;

use Silex\Application;
use Silex\ControllerProviderInterface;
use Symfony\Component\HttpFoundation\Response;

class BlobController implements ControllerProviderInterface
{
    public function connect(Application $app)
    {
        $route = $app['controllers_factory'];

        $route->get('{repo}/blob/{commitishPath}', function ($repo, $commitishPath) use ($app) {
            $repository = $app['git']->getRepositoryFromName($app['git.repos'], $repo);

            list($branch, $file) = $app['util.routing']
                ->parseCommitishPathParam($commitishPath, $repo);

            list($branch, $file) = $app['util.repository']->extractRef($repository, $branch, $file);

            $blob = $repository->getBlob("$branch:\"$file\"");
            $breadcrumbs = $app['util.view']->getBreadcrumbs($file);
            $fileType = $app['util.repository']->getFileType($file);

            $bare = ($app['git.editor']) ? $repository->getConfig("core.bare") : $bare = "true";
            $sourceFilePath = $repository->getPath().$file;
            $writeable = (is_writeable($sourceFilePath)) ? true :false;

            if ($fileType !== 'image' && $app['util.repository']->isBinary($file)) {
                return $app->redirect($app['url_generator']->generate('blob_raw', array(
                    'repo'   => $repo,
                    'commitishPath' => $commitishPath,
                )));
            }

            return $app['twig']->render('file.twig', array(
                'file'           => $file,
                'fileType'       => $fileType,
                'blob'           => $blob->output(),
                'repo'           => $repo,
                'branch'         => $branch,
                'breadcrumbs'    => $breadcrumbs,
                'branches'       => $repository->getBranches(),
                'tags'           => $repository->getTags(),
                'bare'           => $bare,
                'writeable'      => $writeable,
            ));
        })->assert('repo', $app['util.routing']->getRepositoryRegex())
          ->assert('commitishPath', '.+')
          ->bind('blob');

        $route->get('{repo}/raw/{commitishPath}', function ($repo, $commitishPath) use ($app) {
            $repository = $app['git']->getRepositoryFromName($app['git.repos'], $repo);

            list($branch, $file) = $app['util.routing']
                ->parseCommitishPathParam($commitishPath, $repo);

            list($branch, $file) = $app['util.repository']->extractRef($repository, $branch, $file);

            $blob = $repository->getBlob("$branch:\"$file\"")->output();

            $headers = array();
            if ($app['util.repository']->isBinary($file)) {
                $headers['Content-Disposition'] = 'attachment; filename="' .  $file . '"';
                $headers['Content-Type'] = 'application/octet-stream';
            } else {
                $headers['Content-Type'] = 'text/plain';
            }

            return new Response($blob, 200, $headers);
        })->assert('repo', $app['util.routing']->getRepositoryRegex())
          ->assert('commitishPath', $app['util.routing']->getCommitishPathRegex())
          ->bind('blob_raw');

        //Edit route
        $route->get('{repo}/edit/{commitishPath}', function ($repo, $commitishPath) use ($app) {
            $repository = $app['git']->getRepositoryFromName($app['git.repos'], $repo);

            list($branch, $file) = $app['util.routing']
                ->parseCommitishPathParam($commitishPath, $repo);

            list($branch, $file) = $app['util.repository']->extractRef($repository, $branch, $file);

            $blob = $repository->getBlob("$branch:\"$file\"");
            $breadcrumbs = $app['util.view']->getBreadcrumbs($file);
            $fileType = $app['util.repository']->getFileType($file);

            $bare = ($app['git.editor']) ? $repository->getConfig("core.bare") : $bare = "true";
            $message = null;

            return $app['twig']->render('edit.twig', array(
                'file'           => $file,
                'fileType'       => $fileType,
                'blob'           => $blob->output(),
                'repo'           => $repo,
                'branch'         => $branch,
                'breadcrumbs'    => $breadcrumbs,
                'branches'       => $repository->getBranches(),
                'tags'           => $repository->getTags(),
                'bare'           => $bare,
                'message'        => $message,
            ));
        })->assert('repo', $app['util.routing']->getRepositoryRegex())
          ->assert('commitishPath', '.+')
          ->bind('blob_edit');

        //Commit route
        $route->post('{repo}/commit/{commitishPath}', function ($repo, $commitishPath) use ($app) {
            $repository = $app['git']->getRepositoryFromName($app['git.repos'], $repo);

            list($branch, $file) = $app['util.routing']
                ->parseCommitishPathParam($commitishPath, $repo);

            list($branch, $file) = $app['util.repository']->extractRef($repository, $branch, $file);

            $blob = $repository->getBlob("$branch:\"$file\"");
            $breadcrumbs = $app['util.view']->getBreadcrumbs($file);
            $fileType = $app['util.repository']->getFileType($file);

            $bare = ($app['git.editor']) ? $repository->getConfig("core.bare") : $bare = "true";

            $sourceFilePath = $repository->getPath();
            $sourceFile = file_get_contents($repository->getPath().DIRECTORY_SEPARATOR.$file);

            if (is_writeable($sourceFilePath)) {
                $data = $_POST['sourcecode_edit'];
                $commit_message = $_POST['commit_message'];

                /* Directly write file from glip
                *  http://fimml.at/glip
                *  Writes the modified data as the same file name in the working directory.
                *  Any change will completely rewrite the file so line level changes are not shown as this time.
                */
                // $modified_file = fopen($sourceFilePath, 'w');
                // flock($modified_file, LOCK_EX);
                // fwrite($modified_file, $data);
                // fclose($modified_file);


                /* Uses UnifiedDiffPatcher and Diff-Match-Patch
                * Check functions to make sure they are the correct names and are passed the necessary vars
                * Check processPatch to see what file it will write to
                * Handle errors with GitList builtin
                * Write tests to check this and the other edits for GitList and Gitter (not sure how)
                */

                $dmp = new DiffMatchPatch\DiffMatchPatch();
                $diff = $dmp->diff_match($data, $sourceFile, false);
                $patch = $dmp->patch_make($diff);

                file_put_contents($repository->getPath().$file."patch", $patch);
                $patchFile = $sourceFilePath."patch";
                $udp = new UnifiedDiffPatcher\UnifiedDiffPatcher();
                $udp->validatePatch($patchFile);
                $udp->processPatch($patchFile);


                $repository->add($file);

                $repository->commit($commit_message);

                $message = $repository->getClient()->run($repository, "log -n 1");

            } else {
                $message = "$file not writeable.";
            }

            return $app['twig']->render('edit.twig', array(
                'file'           => $file,
                'fileType'       => $fileType,
                'blob'           => $blob->output(),
                'repo'           => $repo,
                'branch'         => $branch,
                'breadcrumbs'    => $breadcrumbs,
                'branches'       => $repository->getBranches(),
                'tags'           => $repository->getTags(),
                'bare'           => $bare,
                'message'        => $message,
            ));
        })->assert('repo', $app['util.routing']->getRepositoryRegex())
          ->assert('commitishPath', '.+')
          ->bind('blob_commit');

        return $route;
    }
}
