<!DOCTYPE html>

<html class="dark" dir="rtl" lang="ar"><head>
<meta charset="utf-8"/>
<meta content="width=device-width, initial-scale=1.0" name="viewport"/>
<title>سجل المخزون والأصناف</title>
<script src="https://cdn.tailwindcss.com?plugins=forms,container-queries"></script>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;700;900&amp;family=Noto+Sans+Arabic:wght@400;500;700;900&amp;display=swap" rel="stylesheet"/>
<link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:wght,FILL@100..700,0..1&amp;display=swap" rel="stylesheet"/>
<link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:wght,FILL@100..700,0..1&amp;display=swap" rel="stylesheet"/>
<script id="tailwind-config">
        tailwind.config = {
            darkMode: "class",
            theme: {
                extend: {
                    colors: {
                        "primary": "#0fbd0f",
                        "background-light": "#f6f8f6",
                        "background-dark": "#102210",
                    },
                    fontFamily: {
                        "display": ["Inter", "Noto Sans Arabic", "sans-serif"],
                        "sans": ["Inter", "Noto Sans Arabic", "sans-serif"]
                    },
                    borderRadius: {"DEFAULT": "0.25rem", "lg": "0.5rem", "xl": "0.75rem", "full": "9999px"},
                },
            },
        }
    </script>
<style>
        body {
            font-family: 'Noto Sans Arabic', 'Inter', sans-serif;
        }
        .material-symbols-outlined {
            font-variation-settings: 'FILL' 0, 'wght' 400, 'GRAD' 0, 'opsz' 24;
        }
    </style>
</head>
<body class="bg-background-light dark:bg-background-dark text-slate-900 dark:text-slate-100 min-h-screen">
<div class="layout-container flex flex-col min-h-screen">
<header class="flex items-center justify-between whitespace-nowrap border-b border-solid border-slate-200 dark:border-[#283928] bg-white dark:bg-[#111811] px-6 py-3 lg:px-10">
<div class="flex items-center gap-8">
<div class="flex items-center gap-3 text-primary">
<div class="size-8 bg-primary/10 rounded-lg flex items-center justify-center">
<span class="material-symbols-outlined text-primary">inventory_2</span>
</div>
<h2 class="text-slate-900 dark:text-white text-lg font-bold leading-tight tracking-tight">نظام إدارة المخازن</h2>
</div>
<nav class="hidden md:flex items-center gap-6">
<a class="text-slate-600 dark:text-slate-300 hover:text-primary dark:hover:text-primary text-sm font-medium transition-colors" href="#">الرئيسية</a>
<a class="text-primary text-sm font-bold border-b-2 border-primary pb-1" href="#">المخازن</a>
<a class="text-slate-600 dark:text-slate-300 hover:text-primary dark:hover:text-primary text-sm font-medium transition-colors" href="#">المبيعات</a>
<a class="text-slate-600 dark:text-slate-300 hover:text-primary dark:hover:text-primary text-sm font-medium transition-colors" href="#">الموردين</a>
<a class="text-slate-600 dark:text-slate-300 hover:text-primary dark:hover:text-primary text-sm font-medium transition-colors" href="#">التقارير</a>
</nav>
</div>
<div class="flex items-center gap-4">
<label class="hidden lg:flex items-center min-w-40 h-10 max-w-64 relative">
<span class="material-symbols-outlined absolute right-3 text-slate-400 dark:text-[#9db99d] text-xl">search</span>
<input class="form-input w-full pr-10 rounded-lg border-slate-200 dark:border-none bg-slate-100 dark:bg-[#283928] text-slate-900 dark:text-white focus:ring-primary focus:border-primary text-sm font-normal" placeholder="بحث سريع..." value=""/>
</label>
<div class="flex gap-2">
<button class="flex items-center justify-center rounded-lg h-10 w-10 bg-slate-100 dark:bg-[#283928] text-slate-600 dark:text-white hover:bg-slate-200 dark:hover:bg-[#344a34] transition-colors">
<span class="material-symbols-outlined">settings</span>
</button>
<button class="flex items-center justify-center rounded-lg h-10 w-10 bg-slate-100 dark:bg-[#283928] text-slate-600 dark:text-white hover:bg-slate-200 dark:hover:bg-[#344a34] transition-colors relative">
<span class="material-symbols-outlined">notifications</span>
<span class="absolute top-2 left-2 size-2 bg-red-500 rounded-full border-2 border-white dark:border-[#283928]"></span>
</button>
</div>
<div class="bg-slate-200 dark:bg-[#283928] aspect-square rounded-full size-10 flex items-center justify-center overflow-hidden border border-slate-300 dark:border-[#3b543b]">
<img class="w-full h-full object-cover" data-alt="صورة الملف الشخصي للمستخدم" src="https://lh3.googleusercontent.com/aida-public/AB6AXuDpIuM5fTP6tU8YttyvVXOqvJ9dXA9LKTKsfntDVXqZQwwY_45ZzCVrNh_AT8IncPYq94UCekDK1i2cK6fmN8YWovqYwVpoGIAGMsELEhbrMrnMZ_MtIDF8x0pUydpbmIx3LTIJR6qrIlYBxDSeE5-sedId12Ls3EYvmPSY713kiY-PlZniAcA75DxAYxYFkhvKn2g82Pmkn0xTsyFrO79z-hbk_WlEJzeq6NJCa0-gzV3tWkIn3SJt2617jYBLiuWtmi6MR_FYtg"/>
</div>
</div>
</header>
<main class="flex-1 overflow-y-auto px-6 lg:px-10 py-8 max-w-[1400px] mx-auto w-full">
<div class="flex flex-col md:flex-row md:items-end justify-between gap-6 mb-8">
<div class="flex flex-col gap-2">
<p class="text-slate-900 dark:text-white text-4xl font-black leading-tight tracking-tight">سجل المخزون والأصناف</p>
<p class="text-slate-500 dark:text-[#9db99d] text-base font-normal">عرض وإدارة كافة أصناف مواد البناء المتوفرة في المستودع</p>
</div>
<div class="flex flex-wrap gap-4">
<div class="flex min-w-[180px] flex-1 flex-col gap-1 rounded-xl p-5 bg-white dark:bg-[#111811] border border-slate-200 dark:border-[#3b543b] shadow-sm">
<p class="text-slate-500 dark:text-[#9db99d] text-sm font-medium">إجمالي الأصناف</p>
<div class="flex items-baseline gap-2">
<p class="text-slate-900 dark:text-white text-2xl font-bold">1,240</p>
<p class="text-primary text-xs font-bold">+5%</p>
</div>
</div>
<div class="flex min-w-[180px] flex-1 flex-col gap-1 rounded-xl p-5 bg-white dark:bg-[#111811] border border-slate-200 dark:border-[#3b543b] shadow-sm">
<p class="text-slate-500 dark:text-[#9db99d] text-sm font-medium">قيمة المخزون</p>
<div class="flex items-baseline gap-2">
<p class="text-slate-900 dark:text-white text-2xl font-bold">450k ر.ي</p>
<p class="text-red-500 text-xs font-bold">-2%</p>
</div>
</div>
<div class="flex min-w-[180px] flex-1 flex-col gap-1 rounded-xl p-5 bg-white dark:bg-[#111811] border border-slate-200 dark:border-[#3b543b] shadow-sm">
<p class="text-slate-500 dark:text-[#9db99d] text-sm font-medium">تنبيهات النقص</p>
<div class="flex items-baseline gap-2">
<p class="text-slate-900 dark:text-white text-2xl font-bold">12</p>
<p class="text-primary text-xs font-bold">+3%</p>
</div>
</div>
</div>
</div>
<div class="bg-white dark:bg-[#111811] rounded-xl border border-slate-200 dark:border-[#3b543b] shadow-sm overflow-hidden mb-10">
<div class="flex flex-col sm:flex-row justify-between items-center gap-4 px-6 py-4 border-b border-slate-100 dark:border-[#283928] bg-slate-50/50 dark:bg-[#1c271c]/30">
<div class="flex items-center gap-2 w-full sm:w-auto">
<button class="p-2 text-slate-600 dark:text-slate-300 hover:bg-slate-200 dark:hover:bg-[#283928] rounded-lg transition-colors flex items-center gap-2">
<span class="material-symbols-outlined text-[22px]">filter_list</span>
<span class="text-sm font-medium hidden sm:inline">تصفية</span>
</button>
<button class="p-2 text-slate-600 dark:text-slate-300 hover:bg-slate-200 dark:hover:bg-[#283928] rounded-lg transition-colors flex items-center gap-2">
<span class="material-symbols-outlined text-[22px]">upload_file</span>
<span class="text-sm font-medium hidden sm:inline">تصدير (Excel)</span>
</button>
<button class="p-2 text-slate-600 dark:text-slate-300 hover:bg-slate-200 dark:hover:bg-[#283928] rounded-lg transition-colors">
<span class="material-symbols-outlined text-[22px]">print</span>
</button>
</div>
<div class="flex items-center gap-3 w-full sm:w-auto">
<div class="relative flex-1 sm:w-64">
<span class="material-symbols-outlined absolute right-3 top-1/2 -translate-y-1/2 text-slate-400 text-lg">search</span>
<input class="w-full pr-10 h-10 text-sm bg-white dark:bg-[#111811] border-slate-200 dark:border-[#3b543b] rounded-lg focus:ring-primary focus:border-primary" placeholder="بحث بالاسم أو الكود..." type="text"/>
</div>
<button class="flex items-center justify-center gap-2 h-10 px-5 bg-primary hover:bg-primary/90 text-white rounded-lg transition-all shadow-sm font-bold text-sm whitespace-nowrap">
<span class="material-symbols-outlined text-[20px] font-bold">add</span>
<span>إضافة صنف</span>
</button>
</div>
</div>
<div class="overflow-x-auto @container">
<table class="w-full text-right border-collapse">
<thead>
<tr class="bg-slate-50 dark:bg-[#1c271c] border-b border-slate-200 dark:border-[#3b543b]">
<th class="px-6 py-4 text-slate-600 dark:text-white text-sm font-bold uppercase tracking-wider w-32">كود الصنف</th>
<th class="px-6 py-4 text-slate-600 dark:text-white text-sm font-bold uppercase tracking-wider">اسم المادة</th>
<th class="px-6 py-4 text-slate-600 dark:text-white text-sm font-bold uppercase tracking-wider w-40">الفئة</th>
<th class="px-6 py-4 text-slate-600 dark:text-white text-sm font-bold uppercase tracking-wider w-40">الكمية المتوفرة</th>
<th class="px-6 py-4 text-slate-600 dark:text-white text-sm font-bold uppercase tracking-wider w-32">سعر التكلفة</th>
<th class="px-6 py-4 text-slate-600 dark:text-white text-sm font-bold uppercase tracking-wider w-32">سعر البيع</th>
<th class="px-6 py-4 text-slate-600 dark:text-white text-sm font-bold uppercase tracking-wider w-40 text-center">تنبيه النقص</th>
<th class="px-6 py-4 text-slate-400 dark:text-[#9db99d] text-sm font-bold uppercase tracking-wider w-24 text-center">إجراءات</th>
</tr>
</thead>
<tbody class="divide-y divide-slate-100 dark:divide-[#283928]">
<tr class="hover:bg-slate-50 dark:hover:bg-[#1c271c]/50 transition-colors">
<td class="px-6 py-4 text-slate-500 dark:text-[#9db99d] text-sm font-mono">BM-001</td>
<td class="px-6 py-4 text-slate-900 dark:text-white text-sm font-medium">أسمنت مقاوم للأملاح</td>
<td class="px-6 py-4">
<span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-slate-100 dark:bg-[#283928] text-slate-600 dark:text-white">مواد أساسية</span>
</td>
<td class="px-6 py-4 text-slate-500 dark:text-[#9db99d] text-sm">500 كيس</td>
<td class="px-6 py-4 text-slate-500 dark:text-[#9db99d] text-sm">25.00 ر.ي</td>
<td class="px-6 py-4 text-slate-500 dark:text-[#9db99d] text-sm">35.00 ر.ي</td>
<td class="px-6 py-4 text-center">
<span class="inline-flex items-center px-3 py-1 rounded-lg text-xs font-bold bg-primary/10 text-primary">متوفر</span>
</td>
<td class="px-6 py-4 text-center">
<button class="text-primary hover:text-primary/80 font-bold text-sm">تعديل</button>
</td>
</tr>
<tr class="hover:bg-slate-50 dark:hover:bg-[#1c271c]/50 transition-colors">
<td class="px-6 py-4 text-slate-500 dark:text-[#9db99d] text-sm font-mono">BM-002</td>
<td class="px-6 py-4 text-slate-900 dark:text-white text-sm font-medium">حديد تسليح سابك 12 ملم</td>
<td class="px-6 py-4">
<span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-slate-100 dark:bg-[#283928] text-slate-600 dark:text-white">حديد</span>
</td>
<td class="px-6 py-4 text-slate-500 dark:text-[#9db99d] text-sm">120 طن</td>
<td class="px-6 py-4 text-slate-500 dark:text-[#9db99d] text-sm">2,800 ر.ي</td>
<td class="px-6 py-4 text-slate-500 dark:text-[#9db99d] text-sm">3,200 ر.ي</td>
<td class="px-6 py-4 text-center">
<span class="inline-flex items-center px-3 py-1 rounded-lg text-xs font-bold bg-orange-100 text-orange-600 dark:bg-orange-500/10 dark:text-orange-400">تنبيه نقص</span>
</td>
<td class="px-6 py-4 text-center">
<button class="text-primary hover:text-primary/80 font-bold text-sm">تعديل</button>
</td>
</tr>
<tr class="hover:bg-slate-50 dark:hover:bg-[#1c271c]/50 transition-colors">
<td class="px-6 py-4 text-slate-500 dark:text-[#9db99d] text-sm font-mono">BM-003</td>
<td class="px-6 py-4 text-slate-900 dark:text-white text-sm font-medium">طوب أحمر مفرغ 20سم</td>
<td class="px-6 py-4">
<span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-slate-100 dark:bg-[#283928] text-slate-600 dark:text-white">طوب</span>
</td>
<td class="px-6 py-4 text-slate-500 dark:text-[#9db99d] text-sm">1500 حبة</td>
<td class="px-6 py-4 text-slate-500 dark:text-[#9db99d] text-sm">1.50 ر.ي</td>
<td class="px-6 py-4 text-slate-500 dark:text-[#9db99d] text-sm">2.20 ر.ي</td>
<td class="px-6 py-4 text-center">
<span class="inline-flex items-center px-3 py-1 rounded-lg text-xs font-bold bg-primary/10 text-primary">متوفر</span>
</td>
<td class="px-6 py-4 text-center">
<button class="text-primary hover:text-primary/80 font-bold text-sm">تعديل</button>
</td>
</tr>
<tr class="hover:bg-slate-50 dark:hover:bg-[#1c271c]/50 transition-colors">
<td class="px-6 py-4 text-slate-500 dark:text-[#9db99d] text-sm font-mono">BM-004</td>
<td class="px-6 py-4 text-slate-900 dark:text-white text-sm font-medium">دهان جوتن بلاستيك مطفي</td>
<td class="px-6 py-4">
<span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-slate-100 dark:bg-[#283928] text-slate-600 dark:text-white">أصباغ</span>
</td>
<td class="px-6 py-4 text-slate-500 dark:text-[#9db99d] text-sm">40 جالون</td>
<td class="px-6 py-4 text-slate-500 dark:text-[#9db99d] text-sm">120 ر.ي</td>
<td class="px-6 py-4 text-slate-500 dark:text-[#9db99d] text-sm">180 ر.ي</td>
<td class="px-6 py-4 text-center">
<span class="inline-flex items-center px-3 py-1 rounded-lg text-xs font-bold bg-red-100 text-red-600 dark:bg-red-500/10 dark:text-red-400">نقص حاد</span>
</td>
<td class="px-6 py-4 text-center">
<button class="text-primary hover:text-primary/80 font-bold text-sm">تعديل</button>
</td>
</tr>
<tr class="hover:bg-slate-50 dark:hover:bg-[#1c271c]/50 transition-colors">
<td class="px-6 py-4 text-slate-500 dark:text-[#9db99d] text-sm font-mono">BM-005</td>
<td class="px-6 py-4 text-slate-900 dark:text-white text-sm font-medium">رمل بناء مغسول ممتاز</td>
<td class="px-6 py-4">
<span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-slate-100 dark:bg-[#283928] text-slate-600 dark:text-white">مواد أساسية</span>
</td>
<td class="px-6 py-4 text-slate-500 dark:text-[#9db99d] text-sm">15 شاحنة</td>
<td class="px-6 py-4 text-slate-500 dark:text-[#9db99d] text-sm">450 ر.ي</td>
<td class="px-6 py-4 text-slate-500 dark:text-[#9db99d] text-sm">600 ر.ي</td>
<td class="px-6 py-4 text-center">
<span class="inline-flex items-center px-3 py-1 rounded-lg text-xs font-bold bg-primary/10 text-primary">متوفر</span>
</td>
<td class="px-6 py-4 text-center">
<button class="text-primary hover:text-primary/80 font-bold text-sm">تعديل</button>
</td>
</tr>
<tr class="hover:bg-slate-50 dark:hover:bg-[#1c271c]/50 transition-colors">
<td class="px-6 py-4 text-slate-500 dark:text-[#9db99d] text-sm font-mono">BM-006</td>
<td class="px-6 py-4 text-slate-900 dark:text-white text-sm font-medium">خشب بليود 18 ملم</td>
<td class="px-6 py-4">
<span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-slate-100 dark:bg-[#283928] text-slate-600 dark:text-white">نجارة</span>
</td>
<td class="px-6 py-4 text-slate-500 dark:text-[#9db99d] text-sm">200 لوح</td>
<td class="px-6 py-4 text-slate-500 dark:text-[#9db99d] text-sm">85.00 ر.ي</td>
<td class="px-6 py-4 text-slate-500 dark:text-[#9db99d] text-sm">115.00 ر.ي</td>
<td class="px-6 py-4 text-center">
<span class="inline-flex items-center px-3 py-1 rounded-lg text-xs font-bold bg-primary/10 text-primary">متوفر</span>
</td>
<td class="px-6 py-4 text-center">
<button class="text-primary hover:text-primary/80 font-bold text-sm">تعديل</button>
</td>
</tr>
</tbody>
</table>
</div>
<div class="px-6 py-4 bg-slate-50 dark:bg-[#1c271c] border-t border-slate-200 dark:border-[#3b543b] flex items-center justify-between">
<p class="text-sm text-slate-500 dark:text-[#9db99d]">عرض 1 إلى 6 من أصل 1,240 صنف</p>
<div class="flex gap-2">
<button class="px-3 py-1 rounded border border-slate-200 dark:border-[#3b543b] text-slate-600 dark:text-white hover:bg-slate-100 dark:hover:bg-[#283928] disabled:opacity-50 transition-colors" disabled="">
<span class="material-symbols-outlined text-sm align-middle">chevron_right</span>
</button>
<button class="px-3 py-1 rounded bg-primary text-white font-bold text-sm">1</button>
<button class="px-3 py-1 rounded border border-slate-200 dark:border-[#3b543b] text-slate-600 dark:text-white hover:bg-slate-100 dark:hover:bg-[#283928] transition-colors">2</button>
<button class="px-3 py-1 rounded border border-slate-200 dark:border-[#3b543b] text-slate-600 dark:text-white hover:bg-slate-100 dark:hover:bg-[#283928] transition-colors">3</button>
<button class="px-3 py-1 rounded border border-slate-200 dark:border-[#3b543b] text-slate-600 dark:text-white hover:bg-slate-100 dark:hover:bg-[#283928] transition-colors">
<span class="material-symbols-outlined text-sm align-middle">chevron_left</span>
</button>
</div>
</div>
</div>
<footer class="mt-auto py-6 text-center border-t border-slate-200 dark:border-[#283928]">
<p class="text-slate-400 dark:text-[#9db99d] text-sm">© 2024 نظام إدارة مخازن مواد البناء - جميع الحقوق محفوظة</p>
</footer>
</main>
</div>
</body></html>