@extends('layouts.app')

@section('title', $moduleName . ' — PROVISION NOC')

@section('content')
<div class="container-fluid py-4 px-4">
    <div class="row justify-content-center align-items-center" style="min-height: calc(100vh - 120px);">
        <div class="col-lg-5 col-md-7 text-center">
            <div style="background: rgba(255,255,255,0.03); border: 1px solid rgba(255,255,255,0.06); border-radius: 20px; padding: 48px 36px; backdrop-filter: blur(12px);">
                <div style="width: 80px; height: 80px; margin: 0 auto 24px; border-radius: 20px; background: linear-gradient(135deg, rgba(59,130,246,0.15), rgba(139,92,246,0.15)); display: flex; align-items: center; justify-content: center; border: 1px solid rgba(99,102,241,0.2);">
                    <i class="fa-solid {{ $moduleIcon }}" style="font-size: 32px; background: linear-gradient(135deg, #60a5fa, #a78bfa); -webkit-background-clip: text; -webkit-text-fill-color: transparent; background-clip: text;"></i>
                </div>
                <h3 style="font-weight: 700; color: rgba(255,255,255,0.9); margin-bottom: 12px;">{{ $moduleName }}</h3>
                <p style="color: rgba(255,255,255,0.45); font-size: 14px; line-height: 1.7; margin-bottom: 24px;">{{ $moduleDescription }}</p>
                <span style="display: inline-block; padding: 6px 18px; border-radius: 20px; background: rgba(245,158,11,0.12); color: #f59e0b; font-size: 12px; font-weight: 600; letter-spacing: 0.05em; border: 1px solid rgba(245,158,11,0.2);">
                    <i class="fa-solid fa-hourglass-half me-1"></i>Coming Soon
                </span>
            </div>
        </div>
    </div>
</div>
@endsection
