<?php

/*
 * This file is part of Phraseanet
 *
 * (c) 2005-2012 Alchemy
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Alchemy\Phrasea\Controller\Prod;

use Alchemy\Phrasea\Controller\RecordsRequest;
use DataURI;
use Silex\Application;
use Silex\ControllerProviderInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 *
 * @license     http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link        www.phraseanet.com
 */
class Tools implements ControllerProviderInterface
{

    public function connect(Application $app)
    {
        $controllers = $app['controllers_factory'];

        $controllers->get('/', function(Application $app, Request $request) {

                $records = RecordsRequest::fromRequest($app, $request, false);

                $metadatas = false;
                $record = null;

                if (count($records) == 1) {
                    $record = $records->first();
                    if ( ! $record->is_grouping()) {
                        try {
                            $reader = new \PHPExiftool\Reader();

                            $metadatas = $reader
                                    ->files($record->get_subdef('document')->get_pathfile())
                                    ->first()->getMetadatas();
                            unset($reader);
                        } catch (\PHPExiftool\Exception\Exception $e) {

                        } catch (\Exception_Media_SubdefNotFound $e) {

                        }
                    }
                }

                $var = array(
                    'helper'    => $records,
                    'selection' => $selection,
                    'record'    => $record,
                    'metadatas' => $metadatas,
                );

                return $app['twig']->render('prod/actions/Tools/index.html.twig', $var);
            });

        $controllers->post('/rotate/', function(Application $app, Request $request) {
                $return = array('success'      => false, 'errorMessage' => '');

                $records = RecordsRequest::fromRequest($app, $request, false);

                $rotation = in_array($request->request->get('rotation'), array('-90', '90', '180')) ? $request->request->get('rotation', 90) : 90;

                foreach ($records as $record) {
                    try {
                        $record->rotate_subdefs($rotation);
                        $return['success'] = true;
                    } catch (\Exception $e) {
                        $return['errorMessage'] = $e->getMessage();
                    }
                }

                return $app->json($return);
            });

        $controllers->post('/image/', function(Application $app, Request $request) {
                $return = array('success' => true);

                $helper = new Helper\Record\Tools($app['phraseanet.core'], $request);

                $selection = $helper->get_elements();

                foreach ($selection as $record) {

                    $substituted = false;
                    foreach ($record->get_subdefs() as $subdef) {
                        if ($subdef->is_substituted()) {
                            $substituted = true;
                            break;
                        }
                    }

                    if ( ! $substituted || $request->request->get('ForceThumbSubstit') == '1') {
                        $record->rebuild_subdefs();
                    }
                }

                return $app->json($return);
            });

        $controllers->post('/hddoc/', function(Application $app, Request $request) {
                $success = false;
                $errorMessage = "";
                $fileName = null;

                if ($file = $request->files->get('newHD')) {

                    if ($file->isValid()) {

                        $fileName = $file->getClientOriginalName();
                        $size = $file->getClientSize();

                        $tempoFile = tempnam(sys_get_temp_dir(), 'substit');
                        unlink($tempoFile);
                        mkdir($tempoFile);
                        $tempoFile = $tempoFile . DIRECTORY_SEPARATOR . $fileName;
                        copy($file->getPathname(), $tempoFile);


                        try {
                            $record = new \record_adapter(
                                    $request->request->get('sbas_id')
                                    , $request->request->get('record_id')
                            );

                            $media = $app['Core']['mediavorus']->guess($tempoFile);

                            $record->substitute_subdef('document', $media);

                            if ((int) $request->request->get('ccfilename') === 1) {
                                $record->set_original_name($fileName);
                            }

                            $success = true;
                        } catch (\Exception $e) {
                            $errorMessage = $e->getMessage();
                        }

                        unlink($tempoFile);
                        rmdir(dirname($tempoFile));
                        unlink($file->getPathname());
                    } else {
                        $errorMessage = _('file is not valid');
                    }
                }

                $template = 'prod/actions/Tools/iframeUpload.html.twig';
                $var = array(
                    'success'      => $success
                    , 'fileName'     => $fileName
                    , 'errorMessage' => $errorMessage
                );

                return new Response($app['twig']->render($template, $var));

                /**
                 *
                 */
            });

        $controllers->post('/chgthumb/', function(Application $app, Request $request) {
                $success = false;
                $errorMessage = "";

                if ($file = $request->files->get('newThumb')) {

                    $size = $file->getClientSize();
                    $fileName = $file->getClientOriginalName();

                    if ($size && $fileName && $file->isValid()) {
                        try {
                            $rootPath = $app['phraseanet.core']->getRegistry()->get('GV_RootPath');
                            $tmpFile = $rootPath . 'tmp/' . $fileName;
                            rename($file->getPathname(), $tmpFile);

                            $record = new \record_adapter(
                                    $request->request->get('sbas_id')
                                    , $request->request->get('record_id')
                            );

                            $media = $app['phraseanet.core']['mediavorus']->guess($file);

                            $record->substitute_subdef('thumbnail', $media);

                            $success = true;
                        } catch (\Exception $e) {
                            $errorMessage = $e->getMessage();
                        }
                    } else {
                        $errorMessage = _('file is not valid');
                    }

                    $template = 'prod/actions/Tools/iframeUpload.html.twig';
                    $var = array(
                        'success'      => $success
                        , 'fileName'     => $fileName
                        , 'errorMessage' => $errorMessage
                    );

                    return new Response($app['twig']->render($template, $var));
                }
            });

        $controllers->post('/thumb-extractor/confirm-box/', function(Application $app, Request $request) {
                $return = array('error'   => false, 'datas'   => '');
                $template = 'prod/actions/Tools/confirm.html.twig';

                try {
                    $record = new \record_adapter($request->request->get('sbas_id'), $request->request->get('record_id'));
                    $var = array(
                        'video_title'    => $record->get_title()
                        , 'image'          => $request->request->get('image', '')
                    );
                    $return['datas'] = $app['twig']->render($template, $var);
                } catch (\Exception $e) {
                    $return['datas'] = _('an error occured');
                    $return['error'] = true;
                }

                return $app->json($return);
            });

        $controllers->post('/thumb-extractor/apply/', function(Application $app, Request $request) {
                $return = array('success' => false, 'message' => '');

                try {
                    $record = new \record_adapter($request->request->get('sbas_id'), $request->request->get('record_id'));

                    $dataUri = DataURI\Parser::parse($request->request->get('image', ''));

                    $path = $app['phraseanet.core']->getRegistry()->get('GV_RootPath') . 'tmp';

                    $name = sprintf('extractor_thumb_%s', $record->get_serialize_key());

                    $fileName = sprintf('%s/%s.png', $path, $name);

                    file_put_contents($fileName, $dataUri->getData());

                    $media = $app['phraseanet.core']['mediavorus']->guess(new \SplFileInfo($fileName));

                    $record->substitute_subdef('thumbnail', $media);

                    unset($media);
                    $app['phraseanet.core']['file-system']->remove($fileName);

                    $return['success'] = true;
                } catch (\Exception $e) {
                    $return['message'] = $e->getMessage();
                }

                return $app->json($return);
            });

        return $controllers;
    }
}
