# TableNutri 🥗🏷️

![Status do Projeto](https://img.shields.io/badge/status-em_desenvolvimento-orange)
![Laravel](https://img.shields.io/badge/Laravel-11-FF2D20?logo=laravel&logoColor=white)
![Filament](https://img.shields.io/badge/Filament-3.x-F28D1A?logo=filament&logoColor=white)
![AI Model](https://img.shields.io/badge/AI-Qwen3--VL:8b-blueviolet)
![Database](https://img.shields.io/badge/Postgres-16-336791?logo=postgresql&logoColor=white)

> **Solução inteligente para geração de tabelas nutricionais compatíveis com o padrão FDA para exportação.**

O **TableNutri** é um sistema completo desenvolvido para simplificar e automatizar a criação de rótulos nutricionais. O diferencial do projeto reside na integração de tecnologias web modernas com **Inteligência Artificial Vision-Language (VLM)** para extração e processamento de dados nutricionais diretamente de embalagens via câmera móvel.

---

## 🚀 Destaques e Arquitetura

O projeto utiliza uma **arquitetura híbrida**, combinando a estabilidade da nuvem com o poder de processamento de hardware local dedicado para inferência de IA.

### 🏗️ Infraestrutura Híbrida
* **Servidor de Produção (Cloud):** Hospedado na **AWS**, rodando Ubuntu com Nginx. Responsável por servir a aplicação web, gerenciar o banco de dados e a interface do usuário.
* **Unidade de Processamento de IA (Edge/Local):** Um servidor de inferência de alto desempenho que executa os modelos de IA localmente.
    * *Benefício:* Redução drástica de custos com APIs de IA externas e garantia de privacidade dos dados, utilizando o poder da GPU dedicada para processamento visual pesado.

### 🧠 Inteligência Artificial
O núcleo de inteligência do sistema utiliza o modelo **Qwen3-VL:8b**.
* **Capacidade:** Modelo *Vision-Language* capaz de interpretar imagens complexas de rótulos.
* **Função:** Extração automática de dados nutricionais a partir de fotos, validação de conformidade e categorização de ingredientes.

---

## 🛠️ Stack Tecnológica

### Backend & Framework
* **[Laravel](https://laravel.com/):** Framework PHP robusto utilizado como espinha dorsal da aplicação.
* **[FilamentPHP](https://filamentphp.com/):** Painel administrativo (TALL stack) para gerenciamento ágil de produtos, usuários e relatórios.

### Banco de Dados & Servidor
* **PostgreSQL:** Banco de dados relacional escolhido pela robustez e suporte a dados complexos.
* **Nginx:** Servidor web de alta performance.
* **Ubuntu:** Sistema operacional base para os ambientes de produção e inferência.

### Ferramentas de Desenvolvimento
* **Laragon:** Ambiente de desenvolvimento local isolado e ágil.

---

## ✨ Funcionalidades Principais

* **📸 Coletor Mobile Inteligente:** Interface otimizada para dispositivos móveis que permite capturar fotos de produtos em tempo real.
* **✂️ Tratamento de Imagem Avançado:** Ferramenta integrada de **Cropping (recorte)** para ajustar o foco na tabela nutricional antes do processamento.
* **🇺🇸 Conformidade FDA:** Algoritmos ajustados para formatar e converter unidades conforme as exigências rigorosas da *Food and Drug Administration* para exportação.
* **📄 Geração de etiquetas:** Exportação automática dos rótulos prontos para impressão em alta definição.
