<?php

namespace GitList\Controller;

use Silex\Application;
use Silex\ControllerProviderInterface;
use Silex\ControllerCollection;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Filesystem\Filesystem;

class BlobController implements ControllerProviderInterface
{
    public function connect(Application $app)
    {
        $route = $app['controllers_factory'];

        $route->get('{repo}/blob/{branch}/{file}', function($repo, $branch, $file) use ($app) {
            $repository = $app['git']->getRepository($app['git.repos'] . $repo);
            list($branch, $file) = $app['util.repository']->extractRef($repository, $branch, $file);

            $blob = $repository->getBlob("$branch:\"$file\"");
            $breadcrumbs = $app['util.view']->getBreadcrumbs($file);
            $fileType = $app['util.repository']->getFileType($file);

            if($app['git.editor']) {
                $bare = $repository->getConfig("core.bare");
            }
            else {
                $bare = "true";
            }

            $sourceFilePath = $repository->getPath().DIRECTORY_SEPARATOR.$file;
            $writeable = false;
            if(is_writeable($sourceFilePath)) {
                $writeable = true;
            }

            if ($fileType !== 'image' && $app['util.repository']->isBinary($file)) {
                return $app->redirect($app['url_generator']->generate('blob_raw', array(
                    'repo'   => $repo,
                    'branch' => $branch,
                    'file'   => $file,
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
        })->assert('file', '.+')
          ->assert('repo', $app['util.routing']->getRepositoryRegex())
          ->assert('branch', '[\w-._\/]+')
          ->bind('blob');

        $route->get('{repo}/raw/{branch}/{file}', function($repo, $branch, $file) use ($app) {
            $repository = $app['git']->getRepository($app['git.repos'] . $repo);
            list($branch, $file) = $app['util.repository']->extractRef($repository, $branch, $file);
            $blob = $repository->getBlob("$branch:\"$file\"")->output();

            $headers = array();
            if ($app['util.repository']->isBinary($file)) {
                $headers['Content-Disposition'] = 'attachment; filename="' .  $file . '"';
                $headers['Content-Transfer-Encoding'] = 'application/octet-stream';
                $headers['Content-Transfer-Encoding'] = 'binary';
            } else {
                $headers['Content-Transfer-Encoding'] = 'text/plain';
            }

            return new Response($blob, 200, $headers);
        })->assert('file', '.+')
          ->assert('repo', $app['util.routing']->getRepositoryRegex())
          ->assert('branch', '[\w-._\/]+')
          ->bind('blob_raw');

                  //Edit route
        $route->get('{repo}/edit/{branch}/{file}', function($repo, $branch, $file) use ($app) {
            $repository = $app['git']->getRepository($app['git.repos'] . $repo);
            $blob = $repository->getBlob("$branch:\"$file\"");
            $breadcrumbs = $app['util.view']->getBreadcrumbs($file);
            $fileType = $app['util.repository']->getFileType($file);

            if($app['git.editor']) {
                $bare = $repository->getConfig("core.bare");
            }
            else {
                $bare = "true";
            }

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
        })->assert('file', '.+')
          ->assert('repo', $app['util.routing']->getRepositoryRegex())
          ->assert('branch', '[\w-._\/]+')
          ->bind('blob_edit');

        //Commit route
        $route->post('{repo}/commit/{branch}/{file}', function($repo, $branch, $file) use ($app) {
            $repository = $app['git']->getRepository($app['git.repos'] . $repo);
            $blob = $repository->getBlob("$branch:\"$file\"");
            $breadcrumbs = $app['util.view']->getBreadcrumbs($file);
            $fileType = $app['util.repository']->getFileType($file);

            if($app['git.editor']) {
                $bare = $repository->getConfig("core.bare");
            }
            else {
                $bare = "true";
            }

            $sourceFilePath = $repository->getPath().DIRECTORY_SEPARATOR.$file;

            if(is_writeable($sourceFilePath)) {
                $data = $_POST['sourcecode_edit'];
                $commit_message = $_POST['commit_message'];

                /* Directly write file from glip
                *  http://fimml.at/glip
                */
                $f = fopen($sourceFilePath, 'w');
                flock($f, LOCK_EX);
                fwrite($f, $data);
                fclose($f);

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
        })->assert('file', '.+')
          ->assert('repo', $app['util.routing']->getRepositoryRegex())
          ->assert('branch', '[\w-._\/]+')
          ->bind('blob_commit');

        return $route;
    }
}
