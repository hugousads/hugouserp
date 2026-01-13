<div>
    {{-- Onboarding Trigger Button (optional - can be placed in header) --}}
    @if(!$showGuide)
        <button 
            wire:click="openGuide"
            type="button"
            class="hidden p-2 text-slate-500 hover:text-slate-700 dark:text-slate-400 dark:hover:text-slate-200 transition-colors"
            data-onboarding-trigger
        >
            <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" d="M9.879 7.519c1.171-1.025 3.071-1.025 4.242 0 1.172 1.025 1.172 2.687 0 3.712-.203.179-.43.326-.67.442-.745.361-1.45.999-1.45 1.827v.75M21 12a9 9 0 11-18 0 9 9 0 0118 0zm-9 5.25h.008v.008H12v-.008z" />
            </svg>
        </button>
    @endif

    {{-- Onboarding Modal --}}
    @if($showGuide && count($steps) > 0)
        <div 
            class="fixed inset-0 z-50 overflow-y-auto"
            aria-labelledby="onboarding-title"
            role="dialog"
            aria-modal="true"
        >
            {{-- Backdrop --}}
            <div class="fixed inset-0 bg-black/50 transition-opacity"></div>

            {{-- Modal Container --}}
            <div class="flex min-h-full items-center justify-center p-4">
                <div class="relative w-full max-w-lg transform overflow-hidden rounded-2xl bg-white dark:bg-slate-800 shadow-2xl transition-all">
                    
                    {{-- Progress Bar --}}
                    <div class="h-1 bg-slate-100 dark:bg-slate-700">
                        <div 
                            class="h-full bg-gradient-to-r from-primary-500 to-primary-600 transition-all duration-500"
                            style="width: {{ ($currentStep + 1) / count($steps) * 100 }}%"
                        ></div>
                    </div>

                    {{-- Step Indicator --}}
                    <div class="flex items-center justify-center gap-2 pt-4 px-6">
                        @foreach($steps as $index => $step)
                            <button
                                wire:click="goToStep({{ $index }})"
                                type="button"
                                class="w-2.5 h-2.5 rounded-full transition-all duration-300 {{ $currentStep === $index ? 'bg-primary-500 scale-125' : ($index < $currentStep ? 'bg-primary-300 dark:bg-primary-700' : 'bg-slate-200 dark:bg-slate-600') }}"
                                aria-label="{{ __('Step :num', ['num' => $index + 1]) }}"
                            ></button>
                        @endforeach
                    </div>

                    {{-- Content --}}
                    @php $currentStepData = $steps[$currentStep] ?? null; @endphp
                    @if($currentStepData)
                        <div class="p-6 text-center">
                            {{-- Icon --}}
                            <div class="mb-4 text-5xl animate-bounce">
                                {{ $currentStepData['icon'] }}
                            </div>

                            {{-- Title --}}
                            <h3 id="onboarding-title" class="text-xl font-semibold text-slate-900 dark:text-slate-100 mb-2">
                                {{ $currentStepData['title'] }}
                            </h3>

                            {{-- Description --}}
                            <p class="text-slate-600 dark:text-slate-400 text-sm leading-relaxed">
                                {{ $currentStepData['description'] }}
                            </p>

                            {{-- Step Counter --}}
                            <p class="mt-4 text-xs text-slate-400 dark:text-slate-500">
                                {{ __('Step :current of :total', ['current' => $currentStep + 1, 'total' => count($steps)]) }}
                            </p>
                        </div>

                        {{-- Actions --}}
                        <div class="flex items-center justify-between p-4 bg-slate-50 dark:bg-slate-700/50 border-t border-slate-100 dark:border-slate-700">
                            <div>
                                @if($currentStep > 0)
                                    <button
                                        wire:click="previousStep"
                                        type="button"
                                        class="px-4 py-2 text-sm font-medium text-slate-600 dark:text-slate-400 hover:text-slate-900 dark:hover:text-slate-200 transition-colors"
                                    >
                                        <svg class="w-4 h-4 inline-block me-1" fill="currentColor" viewBox="0 0 20 20">
                                            <path fill-rule="evenodd" d="M7.707 14.707a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414l4-4a1 1 0 011.414 1.414L5.414 9H17a1 1 0 110 2H5.414l2.293 2.293a1 1 0 010 1.414z" clip-rule="evenodd" />
                                        </svg>
                                        {{ __('Previous') }}
                                    </button>
                                @else
                                    <button
                                        wire:click="skipOnboarding"
                                        type="button"
                                        class="px-4 py-2 text-sm font-medium text-slate-500 dark:text-slate-400 hover:text-slate-700 dark:hover:text-slate-200 transition-colors"
                                    >
                                        {{ __('Skip Tour') }}
                                    </button>
                                @endif
                            </div>
                            
                            <div>
                                <button
                                    wire:click="nextStep"
                                    type="button"
                                    class="px-5 py-2 text-sm font-medium text-white bg-primary-600 hover:bg-primary-700 rounded-lg transition-colors shadow-sm"
                                >
                                    @if($currentStep === count($steps) - 1)
                                        {{ __('Get Started!') }}
                                        <svg class="w-4 h-4 inline-block ms-1" fill="currentColor" viewBox="0 0 24 24">
                                            <path fill-rule="evenodd" d="M9.315 7.584C12.195 3.883 16.695 1.5 21.75 1.5a.75.75 0 01.75.75c0 5.056-2.383 9.555-6.084 12.436A6.75 6.75 0 019.75 22.5a.75.75 0 01-.75-.75v-4.131A15.838 15.838 0 016.382 15H2.25a.75.75 0 01-.75-.75 6.75 6.75 0 017.815-6.666zM15 6.75a2.25 2.25 0 100 4.5 2.25 2.25 0 000-4.5z" clip-rule="evenodd" />
                                            <path d="M5.26 17.242a.75.75 0 10-.897-1.203 5.243 5.243 0 00-2.05 5.022.75.75 0 00.625.627 5.243 5.243 0 005.022-2.051.75.75 0 10-1.202-.897 3.744 3.744 0 01-3.008 1.51c0-1.23.592-2.323 1.51-3.008z" />
                                        </svg>
                                    @else
                                        {{ __('Next') }}
                                        <svg class="w-4 h-4 inline-block ms-1" fill="currentColor" viewBox="0 0 20 20">
                                            <path fill-rule="evenodd" d="M10.293 3.293a1 1 0 011.414 0l6 6a1 1 0 010 1.414l-6 6a1 1 0 01-1.414-1.414L14.586 11H3a1 1 0 110-2h11.586l-4.293-4.293a1 1 0 010-1.414z" clip-rule="evenodd" />
                                        </svg>
                                    @endif
                                </button>
                            </div>
                        </div>
                    @endif

                    {{-- Close Button --}}
                    <button
                        wire:click="closeGuide"
                        type="button"
                        class="absolute top-3 end-3 p-1 text-slate-400 hover:text-slate-600 dark:hover:text-slate-200 transition-colors"
                        aria-label="{{ __('Close') }}"
                    >
                        <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                        </svg>
                    </button>
                </div>
            </div>
        </div>
    @endif
</div>
