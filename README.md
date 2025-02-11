# Laravel Media

## 📌 Sobre o Projeto
O **Media For Laravel** é um package que extende funcionalidades do spatie/laravel-medialibrary.

## ✨ Funcionalidades
- 🏷 **Upload de arquivos** você pode fazer upload de arquivos sem burocracia
- 🏷 **Upload de arquivos temporarios** upload de arquivos temporarios para não encher seu armazenamento
- 🏷 **Suporte S3** compativel com qualquer armazenamento s3

---

## 🚀 Instalação

### 1️⃣ Requisitos
Antes de instalar, certifique-se de que seu projeto atenda aos seguintes requisitos:
- PHP >= 8.0
- Laravel >= 10
- Composer instalado

### 2️⃣ Instalação do Package
Execute o seguinte comando no terminal:
```bash
  composer risetechapps/media-for-laravel
```

---

## 🔑 Autenticação via API

### 🔹 Rota de Login
**Endpoint:** `/uploads`
**Método:** `POST`

#### Exemplo de Requisição:
```json
{
    "file": "example.text",
    "collection": "uploads"
}
```

#### Exemplo de Resposta:

```json
{
    "success": true,
    "data": {
        "id": "xxxxxxxxx",
        "name": "example",
        "type": "application/text",
        "size": "100",
        "preview": "https://preview/xxxxxx",
        "collection": "uploads"
    }
}
```


---

## 🛠 Contribuição
Sinta-se à vontade para contribuir! Basta seguir estes passos:
1. Faça um fork do repositório
2. Crie uma branch (`feature/nova-funcionalidade`)
3. Faça um commit das suas alterações
4. Envie um Pull Request

---

## 📜 Licença
Este projeto é distribuído sob a licença MIT. Veja o arquivo [LICENSE](LICENSE) para mais detalhes.

---

💡 **Desenvolvido por [Rise Tech](https://risetech.com.br)**

