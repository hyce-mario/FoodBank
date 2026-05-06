<div class="flex gap-1 flex-wrap mb-5 bg-white border border-gray-200 rounded-xl p-1 shadow-sm">
    <a href="{{ route('finance.dashboard') }}"
       class="px-4 py-2 text-sm font-medium rounded-lg transition-colors {{ request()->routeIs('finance.dashboard') ? 'bg-navy-700 text-white' : 'text-gray-600 hover:bg-gray-100' }}">
        Dashboard
    </a>
    <a href="{{ route('finance.transactions.index') }}"
       class="px-4 py-2 text-sm font-medium rounded-lg transition-colors {{ request()->routeIs('finance.transactions.*') ? 'bg-navy-700 text-white' : 'text-gray-600 hover:bg-gray-100' }}">
        Transactions
    </a>
    <a href="{{ route('finance.categories.index') }}"
       class="px-4 py-2 text-sm font-medium rounded-lg transition-colors {{ request()->routeIs('finance.categories.*') ? 'bg-navy-700 text-white' : 'text-gray-600 hover:bg-gray-100' }}">
        Categories
    </a>
    <a href="{{ route('finance.budgets.index') }}"
       class="px-4 py-2 text-sm font-medium rounded-lg transition-colors {{ request()->routeIs('finance.budgets.*') ? 'bg-navy-700 text-white' : 'text-gray-600 hover:bg-gray-100' }}">
        Budgets
    </a>
    <a href="{{ route('finance.pledges.index') }}"
       class="px-4 py-2 text-sm font-medium rounded-lg transition-colors {{ request()->routeIs('finance.pledges.*') ? 'bg-navy-700 text-white' : 'text-gray-600 hover:bg-gray-100' }}">
        Pledges
    </a>
    <a href="{{ route('finance.reports') }}"
       class="px-4 py-2 text-sm font-medium rounded-lg transition-colors {{ request()->routeIs('finance.reports') ? 'bg-navy-700 text-white' : 'text-gray-600 hover:bg-gray-100' }}">
        Reports
    </a>
</div>
