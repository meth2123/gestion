<?php
class PayDunyaSDK {
    private $master_key;
    private $public_key;
    private $private_key;
    private $token;
    private $mode;
    private $store;
    private $base_url;

    private $payment_channels = [
        'orange-money-senegal',
        'wave-senegal',
        'free-money-senegal'
    ];

    public function __construct($config) {
        // Vérifier que toutes les clés requises sont présentes
        $required_keys = ['api_keys', 'mode', 'store'];
        foreach ($required_keys as $key) {
            if (!isset($config[$key])) {
                throw new Exception("Clé de configuration manquante : $key");
            }
        }

        // Vérifier les clés API requises
        $required_api_keys = ['master_key', 'public_key', 'private_key', 'token'];
        foreach ($required_api_keys as $key) {
            if (!isset($config['api_keys'][$key])) {
                throw new Exception("Clé API manquante : $key");
            }
        }

        $this->master_key = $config['api_keys']['master_key'] ?? '';
        $this->public_key = $config['api_keys']['public_key'] ?? '';
        $this->private_key = $config['api_keys']['private_key'] ?? '';
        $this->token = $config['api_keys']['token'] ?? '';
        $this->mode = $config['mode'] ?? 'live';
        $this->store = $config['store'] ?? [];
        
        // URL de base toujours la même en production
        $this->base_url = 'https://app.paydunya.com/api/v1';
        
        error_log("PayDunya SDK initialisé - Mode: " . strtoupper($this->mode));
        error_log("URL de base: " . $this->base_url);
        error_log("Clés API configurées :");
        error_log("Master Key: " . (is_string($this->master_key) ? substr($this->master_key, 0, 5) . '...' : 'non définie'));
        error_log("Public Key: " . (is_string($this->public_key) ? substr($this->public_key, 0, 5) . '...' : 'non définie'));
        error_log("Private Key: " . (is_string($this->private_key) ? substr($this->private_key, 0, 5) . '...' : 'non définie'));
        error_log("Token: " . (is_string($this->token) ? substr($this->token, 0, 5) . '...' : 'non défini'));
    }

    public function createInvoice($data) {
        $endpoint = $this->base_url . '/checkout-invoice/create';
        error_log("Tentative de création de facture - Endpoint: " . $endpoint);
        
        // Vérifier que les clés API ne sont pas vides
        if (empty($this->master_key) || empty($this->public_key) || empty($this->private_key) || empty($this->token)) {
            throw new Exception("Une ou plusieurs clés API PayDunya sont manquantes ou vides. Veuillez vérifier votre configuration.");
        }
        
        // Vérifier que les clés ne sont pas les valeurs par défaut de test (si en mode production)
        if ($this->mode === 'live') {
            $test_master_key = 'J8Bk1t8t-AWZp-kVD1-WbjB-CndDy4hrVS7J';
            if ($this->master_key === $test_master_key) {
                error_log("ATTENTION: Clés de test détectées en mode production. Assurez-vous d'utiliser vos clés de production.");
            }
        }
        
        // Vérifier que les URLs ne sont pas en localhost
        $website_url = $this->store['website_url'] ?? '';
        $callback_url = $this->store['callback_url'] ?? '';
        $cancel_url = $this->store['cancel_url'] ?? '';
        $return_url = $this->store['return_url'] ?? '';

        if (is_string($website_url) && strpos($website_url, 'localhost') !== false) {
            throw new Exception("L'URL du site web ne peut pas être en localhost. Utilisez une URL publique (ex: ngrok)");
        }
        
        if ((is_string($callback_url) && strpos($callback_url, 'localhost') !== false) ||
            (is_string($cancel_url) && strpos($cancel_url, 'localhost') !== false) ||
            (is_string($return_url) && strpos($return_url, 'localhost') !== false)) {
            throw new Exception("Les URLs de callback ne peuvent pas être en localhost. Utilisez des URLs publiques (ex: ngrok)");
        }

        $payload = [
            'invoice' => [
                'items' => $data['items'],
                'total_amount' => $data['total_amount'],
                'description' => $data['description'],
                'custom_data' => $data['custom_data'] ?? [],
            ],
            'store' => [
                'name' => $this->store['name'],
                'tagline' => $this->store['tagline'],
                'postal_address' => $this->store['postal_address'],
                'phone' => $this->store['phone_number'],
                'website_url' => $this->store['website_url'],
            ],
            'actions' => [
                'callback_url' => $this->store['callback_url'],
                'cancel_url' => $this->store['cancel_url'],
                'return_url' => $this->store['return_url'],
            ],
            'channels' => $this->payment_channels // Utilisation des canaux de paiement prédéfinis
        ];

        error_log("Payload de la requête: " . json_encode($payload, JSON_PRETTY_PRINT));
        
        $response = $this->makeRequest('POST', $endpoint, $payload);
        
        // Log de la réponse complète pour le débogage
        error_log("Réponse complète de PayDunya: " . json_encode($response, JSON_PRETTY_PRINT));
        
        // Vérification des codes de réponse PayDunya
        if (isset($response['response_code'])) {
            $response_code = $response['response_code'];
            $response_text = $response['response_text'] ?? 'Aucun message';
            
            error_log("Code de réponse PayDunya: $response_code");
            error_log("Message de réponse: $response_text");
            
            switch ($response_code) {
                case '00':
                    // La facture a été créée avec succès
                    if (isset($response['token']) && isset($response['response_text'])) {
                        error_log("Facture créée avec succès - Token: " . $response['token']);
                        return [
                            'success' => true,
                            'token' => $response['token'],
                            'invoice_url' => $response['response_text'], // L'URL de paiement est dans response_text
                            'description' => $response['description'] ?? 'Facture créée avec succès'
                        ];
                    }
                    break;
                case '1001':
                    // Code 1001 peut avoir plusieurs causes
                    // Vérifier le message de réponse pour plus de détails
                    $error_message = "Compte PayDunya non activé ou problème de configuration.";
                    if (!empty($response_text) && $response_text !== 'Aucun message') {
                        $error_message .= " Détails: " . $response_text;
                    } else {
                        $error_message .= " Veuillez vérifier : 1) L'activation complète de votre compte PayDunya (y compris la validation email et SMS), 2) La complétion du formulaire KYC, 3) La correspondance entre vos clés API et votre compte activé.";
                    }
                    error_log("ERREUR 1001 - Réponse complète: " . json_encode($response));
                    throw new Exception($error_message);
                case '1002':
                    $error_message = "Clés API PayDunya invalides.";
                    if (!empty($response_text) && $response_text !== 'Aucun message') {
                        $error_message .= " Détails: " . $response_text;
                    } else {
                        $error_message .= " Veuillez vérifier que vos clés API (MASTER_KEY, PUBLIC_KEY, PRIVATE_KEY, TOKEN) sont correctes et correspondent à votre compte PayDunya.";
                    }
                    error_log("ERREUR 1002 - Réponse complète: " . json_encode($response));
                    throw new Exception($error_message);
                case '1003':
                    $error_message = "Montant invalide.";
                    if (!empty($response_text) && $response_text !== 'Aucun message') {
                        $error_message .= " Détails: " . $response_text;
                    } else {
                        $error_message .= " Le montant doit être supérieur à 0.";
                    }
                    error_log("ERREUR 1003 - Réponse complète: " . json_encode($response));
                    throw new Exception($error_message);
                default:
                    $error_message = "Erreur PayDunya (Code: $response_code)";
                    if (!empty($response_text) && $response_text !== 'Aucun message') {
                        $error_message .= ": " . $response_text;
                    } else {
                        $error_message .= ". Réponse complète: " . json_encode($response);
                    }
                    error_log("ERREUR INCONNUE - Code: $response_code - Réponse complète: " . json_encode($response));
                    throw new Exception($error_message);
            }
        }

        throw new Exception("Réponse invalide de l'API PayDunya: " . json_encode($response));
    }

    public function confirmInvoice($token) {
        $endpoint = $this->base_url . '/checkout-invoice/confirm/' . $token;
        $response = $this->makeRequest('GET', $endpoint);
        
        if (isset($response['status'])) {
            return [
                'status' => $response['status'],
                'receipt_url' => $response['receipt_url'] ?? null,
                'custom_data' => $response['custom_data'] ?? []
            ];
        }

        throw new Exception("Erreur lors de la confirmation de la facture");
    }

    private function makeRequest($method, $endpoint, $data = null) {
        error_log("Requête PayDunya - Méthode: $method, Endpoint: $endpoint");
        
        $ch = curl_init($endpoint);
        
        // Formatage des en-têtes selon la documentation PayDunya
        // Nettoyer et valider chaque clé avant l'envoi
        $master_key_clean = is_string($this->master_key) ? trim($this->master_key) : '';
        $public_key_clean = is_string($this->public_key) ? trim($this->public_key) : '';
        $private_key_clean = is_string($this->private_key) ? trim($this->private_key) : '';
        $token_clean = is_string($this->token) ? trim($this->token) : '';
        
        // Vérifier que les clés nettoyées ne sont pas vides
        if (empty($master_key_clean) || empty($public_key_clean) || empty($private_key_clean) || empty($token_clean)) {
            error_log("ERREUR: Une ou plusieurs clés API sont vides après nettoyage");
            error_log("Master Key: " . (empty($master_key_clean) ? 'VIDE' : substr($master_key_clean, 0, 10) . '...'));
            error_log("Public Key: " . (empty($public_key_clean) ? 'VIDE' : substr($public_key_clean, 0, 10) . '...'));
            error_log("Private Key: " . (empty($private_key_clean) ? 'VIDE' : substr($private_key_clean, 0, 10) . '...'));
            error_log("Token: " . (empty($token_clean) ? 'VIDE' : substr($token_clean, 0, 10) . '...'));
            throw new Exception("Une ou plusieurs clés API PayDunya sont vides. Veuillez vérifier votre configuration.");
        }
        
        $headers = [
            'PAYDUNYA-MASTER-KEY: ' . $master_key_clean,
            'PAYDUNYA-PUBLIC-KEY: ' . $public_key_clean,
            'PAYDUNYA-PRIVATE-KEY: ' . $private_key_clean,
            'PAYDUNYA-TOKEN: ' . $token_clean,
            'Content-Type: application/json',
            'Accept: application/json'
        ];

        error_log("Headers de la requête: " . json_encode($headers, JSON_PRETTY_PRINT));

        // Options cURL de base
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        
        // Configuration SSL - Toujours activée en production
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);
        error_log("Mode " . strtoupper($this->mode) . " : Vérification SSL activée");

        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);
        
        // Options spécifiques à la méthode
        if ($method === 'POST') {
            curl_setopt($ch, CURLOPT_POST, true);
            if ($data) {
                $json_data = json_encode($data);
                curl_setopt($ch, CURLOPT_POSTFIELDS, $json_data);
                error_log("Données POST: " . $json_data);
            }
        }

        // Activer le débogage cURL
        $verbose = fopen('php://temp', 'w+');
        curl_setopt($ch, CURLOPT_VERBOSE, true);
        curl_setopt($ch, CURLOPT_STDERR, $verbose);

        // Exécuter la requête
        $response = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        
        // Récupérer les informations de débogage
        rewind($verbose);
        $verbose_log = stream_get_contents($verbose);
        
        // Vérifier les erreurs cURL
        if ($response === false) {
            $error = curl_error($ch);
            $errno = curl_errno($ch);
            curl_close($ch);
            fclose($verbose);
            
            error_log("Erreur cURL ($errno): $error");
            error_log("Log cURL: " . $verbose_log);
            
            throw new Exception("Erreur de connexion à l'API PayDunya: $error");
        }

        curl_close($ch);
        fclose($verbose);

        // Log de la réponse pour le débogage
        error_log("Réponse PayDunya (HTTP $http_code): " . $response);
        
        // Vérifier si la réponse est une page HTML (erreur 404, etc.)
        if (strpos($response, '<!DOCTYPE html>') !== false) {
            error_log("Réponse HTML reçue au lieu de JSON");
            throw new Exception("Erreur API PayDunya: Réponse HTML inattendue (HTTP $http_code)");
        }
        
        // Décoder la réponse JSON
        $decoded_response = json_decode($response, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            error_log("Erreur de décodage JSON: " . json_last_error_msg());
            error_log("Réponse brute: " . $response);
            throw new Exception("Erreur de format de réponse de l'API PayDunya");
        }

        // Vérifier le code HTTP
        if ($http_code >= 200 && $http_code < 300) {
            return $decoded_response;
        }

        // Gérer les erreurs spécifiques
        $error_message = isset($decoded_response['message']) 
            ? $decoded_response['message'] 
            : "Erreur inconnue (HTTP $http_code)";
            
        throw new Exception("Erreur API PayDunya: $error_message");
    }
} 