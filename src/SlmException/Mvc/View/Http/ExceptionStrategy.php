<?php
/**
 * Copyright (c) 2012-2013 Jurian Sluiman.
 * All rights reserved.
 *
 * Redistribution and use in source and binary forms, with or without
 * modification, are permitted provided that the following conditions
 * are met:
 *
 *   * Redistributions of source code must retain the above copyright
 *     notice, this list of conditions and the following disclaimer.
 *
 *   * Redistributions in binary form must reproduce the above copyright
 *     notice, this list of conditions and the following disclaimer in
 *     the documentation and/or other materials provided with the
 *     distribution.
 *
 *   * Neither the names of the copyright holders nor the names of the
 *     contributors may be used to endorse or promote products derived
 *     from this software without specific prior written permission.
 *
 * THIS SOFTWARE IS PROVIDED BY THE COPYRIGHT HOLDERS AND CONTRIBUTORS
 * "AS IS" AND ANY EXPRESS OR IMPLIED WARRANTIES, INCLUDING, BUT NOT
 * LIMITED TO, THE IMPLIED WARRANTIES OF MERCHANTABILITY AND FITNESS
 * FOR A PARTICULAR PURPOSE ARE DISCLAIMED. IN NO EVENT SHALL THE
 * COPYRIGHT OWNER OR CONTRIBUTORS BE LIABLE FOR ANY DIRECT, INDIRECT,
 * INCIDENTAL, SPECIAL, EXEMPLARY, OR CONSEQUENTIAL DAMAGES (INCLUDING,
 * BUT NOT LIMITED TO, PROCUREMENT OF SUBSTITUTE GOODS OR SERVICES;
 * LOSS OF USE, DATA, OR PROFITS; OR BUSINESS INTERRUPTION) HOWEVER
 * CAUSED AND ON ANY THEORY OF LIABILITY, WHETHER IN CONTRACT, STRICT
 * LIABILITY, OR TORT (INCLUDING NEGLIGENCE OR OTHERWISE) ARISING IN
 * ANY WAY OUT OF THE USE OF THIS SOFTWARE, EVEN IF ADVISED OF THE
 * POSSIBILITY OF SUCH DAMAGE.
 *
 * @package     SlmException
 * @author      Jurian Sluiman <jurian@juriansluiman.nl>
 * @copyright   2012-2013 Jurian Sluiman http://juriansluiman.nl.
 * @license     http://www.opensource.org/licenses/bsd-license.php  BSD License
 */

namespace SlmException\Mvc\View\Http;

use Zend\Mvc\View\Http\ExceptionStrategy as BaseExceptionStrategy;
use Zend\Mvc\MvcEvent;
use Zend\Stdlib\ResponseInterface as Response;
use Zend\Mvc\Application;
use Zend\View\Model\ViewModel;
use Zend\Filter\Word\CamelCaseToDash as CamelCaseToDashFilter;
use Zend\Http\Response as HttpResponse;

use SlmException\Exception;

class ExceptionStrategy extends BaseExceptionStrategy
{
    protected $defaultMarker;
    protected $exceptionMarkers;

    public function setDefaultMarker($marker)
    {
        $this->defaultMarker = $marker;
    }

    public function setExceptionMarkers(array $markers)
    {
        $this->exceptionMarkers = $markers;
    }

    public function prepareExceptionViewModel(MvcEvent $e)
    {
        // Do nothing if no error in the event
        $error = $e->getError();
        if (empty($error)) {
            return;
        }

        // Do nothing if the result is a response object
        $result = $e->getResult();
        if ($result instanceof Response) {
            return;
        }

        // Do nothing if the error is not triggered during dispatch
        if ($error !== Application::ERROR_EXCEPTION) {
            return;
        }

        // Do nothing when exception is not marked as an Error\Exception\ExceptionInterface
        $exception = $e->getParam('exception');
        if (!is_object($exception) || !$exception instanceof Exception\ExceptionInterface) {
            return;
        }

        // Do nothing when code cannot be found
        $name = $this->checkExceptionCode($exception);
        if (null === $name) {
            return;
        }

        $model = new ViewModel(array(
            'message'            => 'An error occurred during execution; please try again later.',
            'exception'          => $exception,
            'display_exceptions' => $this->displayExceptions(),
        ));

        $template = $this->getTemplatename($name);
        $model->setTemplate($template);
        $e->setResult($model);

        $response = $e->getResponse();
        if (!$response) {
            $response = new HttpResponse();
            $e->setResponse($response);
        }

        $code = $this->exceptionMarkers[$name];
        $response->setStatusCode($code);

        // Unset error to stop other exception listeners from triggering
        $e->setError(null);
    }

    /**
     * Check if we recognize the exception and can parse it to a HTTP status code
      *
     * @param  Exception\ExceptionInterface $exception Exception thrown
     * @return string                                  Name of the exception we know
     */
    protected function checkExceptionCode(Exception\ExceptionInterface $exception)
    {
        $interfaces = class_implements($exception);
        foreach($interfaces as $name) {
            if (isset($this->exceptionMarkers[$name])) {
                return $name;
            }
        }
    }

    /**
     * Get template name based on exception interface name
     *
     * @param  string $exceptionName Name of the Exception interface
     * @return string                Name of the template name
     */
    protected function getTemplatename($name)
    {
        /**
         * Strip namespace part and Interface suffix
         *
         * This code only assumes that when the string
         * "Interface" is present, it is the suffix of
         * the interface's class name. This is in most
         * cases true, but custom interfaces might not
         * rely on this naming convention.
         *
         * Example: Foo\Bar\BazInterface gives "Baz"
         */
        $name = substr($name, strrpos($name, '\\') + 1);
        if (strpos($name, 'Interface') !== false) {
            $name = substr($name, 0, -9);
        }

        $filter   = new CamelCaseToDashFilter;
        $name     = $filter->filter($name);
        $template = 'error/' . strtolower($name);

        return $template;
    }
}