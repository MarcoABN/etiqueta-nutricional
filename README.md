<p align="center">
    <img src="https://raw.githubusercontent.com/laravel/art/master/logo-lockup/5%20SVG/2%20CMYK/1%20Full%20Color/laravel-logolockup-cmyk-red.svg" width="300" alt="TableNutri Logo">
    </p>

<p align="center">
    <a href="https://laravel.com"><img src="https://img.shields.io/badge/Laravel-10.x-red?style=for-the-badge&logo=laravel" alt="Laravel"></a>
    <a href="https://filamentphp.com"><img src="https://img.shields.io/badge/Filament-3.x-amber?style=for-the-badge&logo=livewire" alt="Filament"></a>
    <a href="https://ollama.com"><img src="https://img.shields.io/badge/AI_Model-Qwen3_VL_8b-blueviolet?style=for-the-badge&logo=openai" alt="AI Model"></a>
    <a href="https://www.fda.gov/food/food-labeling-nutrition"><img src="https://img.shields.io/badge/Compliance-FDA-green?style=for-the-badge&logo=shield" alt="FDA Compliant"></a>
</p>

# TableNutri - Gerador de Tabelas Nutricionais (FDA)

O **TableNutri** é um sistema especializado na criação e gestão de tabelas nutricionais em conformidade com as normas da **FDA (Food and Drug Administration)**. O projeto visa simplificar o processo de exportação de produtos alimentícios, automatizando a extração de dados de embalagens e gerando rótulos prontos para impressão.

## 🧠 Arquitetura Híbrida & IA

O grande diferencial do TableNutri é sua arquitetura de Inteligência Artificial híbrida. O sistema utiliza **Vision Language Models (VLM)** para ler e interpretar fotos de embalagens em tempo real.

- **Modelo de IA:** `qwen3-vl:8b` (Otimizado para leitura de textos em imagens/OCR contextual).
- **Processamento:** Ocorre em um servidor dedicado de alta performance (Home Lab com **RTX 4070 12GB** + **Ryzen 7 5700X3D**).
- **Produção:** O sistema web roda na nuvem (**Amazon Lightsail**), comunicando-se via túnel seguro com a API de inferência local.

## ✨ Funcionalidades Principais

- **📸 Coleta Mobile Inteligente:**
  - Captura de fotos de produtos diretamente pelo celular.
  - Ferramenta de **Cropping (Recorte)** integrada para focar na tabela nutricional ou lista de ingredientes.
  
- **🤖 Extração Automática (AI-Powered):**
  - O sistema lê a imagem enviada e extrai automaticamente: *Calorias, Gorduras, Carboidratos, Proteínas, Vitaminas e Ingredientes*.
  - Conversão inteligente de unidades para o padrão americano (ex: *g* para *oz*, *kJ* para *kcal*).

- **🇺🇸 Geração de Rótulos FDA:**
  - Layout automático seguindo o padrão vertical/horizontal exigido pelos EUA.
  - Cálculo automático de *Daily Value* (%DV) baseado nas regras da FDA 2024.

- **📦 Gestão de Produtos:**
  - Histórico de versões de rótulos.
  - Exportação em PDF de alta resolução para gráficas.

## 🛠️ Stack Tecnológica

- **Backend:** Laravel 10 (PHP 8.2+)
- **Painel Administrativo:** FilamentPHP v3
- **Banco de Dados:** PostgreSQL
- **Infraestrutura Web:** Ubuntu Server + Nginx (Amazon Lightsail)
- **AI Inference Server:** Ollama (Local Host com GPU Nvidia)
- **Frontend:** Livewire + Alpine.js + Cropper.js

## 🚀 Instalação (Ambiente de Desenvolvimento)

Para rodar o projeto localmente, você precisará do **Laragon** (ou Docker) e acesso a uma instância do Ollama.
