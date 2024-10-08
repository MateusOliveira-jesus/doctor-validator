<?php
class api_logic
{
    private $endpoint;
    private $params;
    // PARAMETROS DA URL PERMITIDOS
    private $permited = [".com", ".br"];
    // PARAMETROS DA URL NÃO PERMITIDOS
    private $noPermited = ["/", "https:"];
    public function __construct($endpoint, $params = null)
    {
        // DEFININDO OS OBJETOS DA CLASS
        $this->endpoint = $endpoint;
        $this->params = $params;
    } // END CONSTRUCT
    public function endpoint_exists()
    {
        // CHECA SE EXISTE EM TODA A CLASSE O ENDPOINT
        return method_exists($this, $this->endpoint);
    } // END ENDPOINT
    public function status()
    {
        return [
            'status' =>  'SUCESS',
            'mensagem' => 'sucesso',
            'results' => null,
        ];
    } // END STATUS
    public function error_response($mensagem = '')
    {
        return [
            'status' =>  'ERROR',
            'mensagem' => $mensagem,
            'results' => null,
        ];
    } // END ERROR RESPOSTA
    public function sucess_response($mensagem = '', $params = [])
    {
        return [
            'status' =>  'SUCESS',
            'mensagem' => $mensagem,
            'results' => $params,
        ];
    } // END SUCESS RESPOSTA

    function validate_url()
    {
        if (empty($this->params['url'])) {
            return $this->error_response("ERRO: Parâmetro URL não fornecido");
        }
        $url = $this->params['url'];

        if (!$this->check_url($url)) {
            return $this->error_response("ERRO: URL Incorreta");
        }
        $links = $this->get_links($url);
        $invalidLinks = [];
        foreach ($links as $key => $link) {
            if ($this->validate_w3c_url($link)['status'] == false) {
                $invalidLinks[$key] = $link;
            }
        }

        if (!empty($invalidLinks)) {
            return [
                'status' => 'ERROR_W3C',
                'mensagem' => "ERRO DE W3C",
                'results' => [
                    "url" => $invalidLinks
                ]
            ];
        }

        return [
            'status' => 'ERROR_W3C',
            'mensagem' => "ERRO DE W3C",
            'results' => [
                "url" => $links
            ]
        ];
    } // END VALIDATOR URL
    private function check_url($url)
    {
        foreach ($this->noPermited as $pattern) {
            if (strpos($url, $pattern) !== false) {
                return false;
            }
        }

        foreach ($this->permited as $pattern) {
            if (strpos($url, $pattern) !== false) {
                return true;
            }
        }

        return false;
    } //END CHECK URL

    private function get_links($dominio)
    {
        
        $url = "https://www.producao.mpitemporario.com.br/{$dominio}";
        //FAZENDO UMA REQUIZIÇÃO PARA URL
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        $html = curl_exec($ch);

        if (curl_errno($ch)) {
            return $this->error_response("ERRO AO TENTAR ACESSAR URL");
        }

        curl_close($ch);

        $dom = new DOMDocument;
        libxml_use_internal_errors(true);
        $dom->loadHTML($html);
        libxml_clear_errors();

        $links = $dom->getElementsByTagName('a');

        $linkArray = [];

        foreach ($links as $link) {
            $href = $link->getAttribute('href');
            if (strpos($href, $url) !== false && strpos($href, "validator") === false) {
                if (!empty($href) && strpos($href, '#') !== 0) {
                    $linkArray[] = $href;
                }
            }
        }

        return $linkArray;
    } //END GET LLINKS
    private function validate_w3c_url($url)
    {
        $api_url = 'https://validator.w3.org/nu/?doc=' . urlencode($url) . '&out=json';
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $api_url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Accept: application/json',
            'User-Agent: PHP-Curl/1.0'
        ]);
        $response = curl_exec($ch);

        if (curl_errno($ch)) {
            curl_close($ch);
            return ['status' => false, 'message' => 'cURL error: ' . curl_error($ch)];
        }

        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($http_code === 404) {
            return ['status' => false, 'message' => 'URL not found (404)'];
        }

        $result = json_decode($response, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            return ['status' => false, 'message' => 'JSON decode error: ' . json_last_error_msg()];
        }

        if (isset($result['messages']) && !empty($result['messages'])) {
            $errors = [];
            $warnings = [];
            foreach ($result['messages'] as $message) {
                if ($message['type'] === 'error') {
                    $errors[] = $message['message'];
                } elseif ($message['type'] === 'info' && isset($message['subtype']) && $message['subtype'] === 'warning') {
                    $warnings[] = $message['message'];
                }
            }

            if (!empty($errors)) {
                return [
                    'status' => false,
                    'message' => 'Validation failed',
                    'errors' => $errors,
                    'warnings' => $warnings
                ];
            } else {
                return [
                    'status' => true,
                    'message' => 'Validation passed with warnings',
                    'warnings' => $warnings
                ];
            }
        }

        return ['status' => true, 'message' => 'Validation passed with no issues'];
    }

    // END VALIDATOR W3C
} // END CLASS
