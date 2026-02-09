<p align="center">
    <img src="https://raw.githubusercontent.com/laravel/art/master/logo-lockup/5%20SVG/2%20CMYK/1%20Full%20Color/laravel-logolockup-cmyk-red.svg" width="300" alt="TableNutri Logo">
</p>

<p align="center">
    <a href="https://laravel.com"><img src="https://img.shields.io/badge/Laravel-10.x-red?style=for-the-badge&logo=laravel" alt="Laravel"></a>
    <a href="https://filamentphp.com"><img src="https://img.shields.io/badge/Filament-3.x-amber?style=for-the-badge&logo=livewire" alt="Filament"></a>
    <a href="https://vuejs.org"><img src="https://img.shields.io/badge/Frontend-Livewire_%2B_Alpine-42b883?style=for-the-badge&logo=vue.js" alt="Frontend"></a>
    <a href="https://www.postgresql.org"><img src="https://img.shields.io/badge/Database-PostgreSQL-316192?style=for-the-badge&logo=postgresql" alt="Postgres"></a>
    <a href="https://ollama.com"><img src="https://img.shields.io/badge/AI_Core-Qwen3_VL_8b-blueviolet?style=for-the-badge&logo=openai" alt="AI Model"></a>
    <a href="https://www.fda.gov/food/food-labeling-nutrition"><img src="https://img.shields.io/badge/Compliance-FDA-green?style=for-the-badge&logo=shield" alt="FDA Compliant"></a>
</p>

# TableNutri - FDA Nutrition Label System

O **TableNutri** é uma plataforma robusta de engenharia de dados nutricionais, desenvolvida para automatizar a criação de rótulos em conformidade com as rígidas normas da **FDA (Food and Drug Administration)**.

O sistema resolve o desafio da exportação de alimentos integrando reconhecimento visual via IA, cálculos nutricionais complexos e gestão de fluxo de trabalho em uma interface unificada.

---

## 🏗️ Arquitetura de Engenharia (Híbrida)

O projeto utiliza uma topologia inovadora para reduzir custos de nuvem enquanto mantém alta capacidade de processamento de IA, utilizando um túnel seguro entre a AWS e um servidor local de alta performance.

```mermaid
graph TD
    User(["📱 Usuário Mobile/Desktop"]) -->|HTTPS| WebServer["☁️ AWS Lightsail (Ubuntu + Nginx)"]
    WebServer -->|Tunnel Criptografado| HomeLab["🏠 Servidor Local (GPU Node)"]
    
    subgraph Cloud ["☁️ Cloud Layer"]
        WebServer
        DB[("PostgreSQL")]
        Queue["Redis Queue"]
    end
    
    subgraph Local ["🏠 AI Inference Layer"]
        HomeLab
        GPU["NVIDIA RTX 4070"]
        Model["Qwen3-VL:8b"]
    end

    HomeLab -->|JSON Estruturado| WebServer
    WebServer -->|PDF/ZPL| User
