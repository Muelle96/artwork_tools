<template>
    <BaseModal @closed="closeUserModal" v-if="show" modal-image="/Svgs/Overlays/illu_user_invite.svg">
            <div class="mx-4">
                <div class="mt-8 headline1">
                    {{ $t('Invite users') }}
                </div>
                <div class="xsLight my-3">
                    {{ $t('You can invite several users with the same user permissions and team memberships at once.') }}
                </div>
                <div class="mt-6">
                    <div class="grid grid-cols-1 md:grid-cols-5">
                        <div class="col-span-4">
                            <TextInputComponent id="email" v-model="emailInput" label="E-Mail*"
                                                @keyup.enter="addEmailToInvitationArray" required/>
                        </div>
                        <div class="col-span-1">
                            <div class="flex items-center h-full justify-center">
                                <button
                                    :class="[emailInput === '' ? 'bg-secondary': 'bg-artwork-buttons-create hover:bg-artwork-buttons-hover focus:outline-none', 'rounded-full mt-2 ml-1 items-center text-sm p-1 border border-transparent uppercase shadow-sm text-secondaryHover']"
                                    @click="addEmailToInvitationArray" :disabled="!emailInput">
                                    <IconCheck stroke-width="1.5" class="h-5 w-5"></IconCheck>
                                </button>
                            </div>
                        </div>
                        <jet-input-error :message="form.error" class="mt-2"/>

                    </div>
                    <ul v-if="showInvalidEmailErrorText">
                        <li class="errorText">{{ $t('This is not a valid e-mail address.')}}</li>
                    </ul>
                    <span v-if="helpText.length > 0" class="text-red-500 text-xs mt-1">
                        {{ helpText }}
                    </span>
                    <span v-for="(email,index) in form.user_emails"
                          class="flex mr-1 rounded-full items-center sDark">
                            {{ email }}
                    <button type="button" @click="deleteEmailFromInvitationArray(index)">
                    <span class="sr-only">{{ $t('Remove email from invitation')}}</span>
                        <IconCircleX stroke-width="1.5"
                            class="ml-1 mt-1 h-5 w-5 hover:text-error "/>
                    </button>
                    </span>
                    <ul class="mt-4">
                        <li class="errorText" v-for="(error,key) in errors" :key="key">
                            {{ error }}
                        </li>
                    </ul>
                    <div class="flex my-3" v-if="form.departments.length > 0">
                        <TeamIconCollection v-for="(team, index) in form.departments" class="h-14 w-14 rounded-full ring-2 ring-white object-cover" :class="index !== 0 ? '-ml-5' : ''" :iconName="team.svg_name"/>
                    </div>
                    <Disclosure as="div">
                        <div class="flex mb-4">
                            <DisclosureButton>
                                <AddButtonSmall :text="$t('Assign to teams')"/>
                            </DisclosureButton>
                            <div v-if="this.$page.props.show_hints && form.departments.length === 0" class="flex mt-2">
                                <SvgCollection svgName="arrowLeft" class="mt-2 ml-2"/>
                                <span class="hind ml-1 my-auto">{{ $t('Assign users directly to your teams')}}</span>
                            </div>
                        </div>
                        <transition enter-active-class="transition-enter-active"
                                    enter-from-class="transition-enter-from"
                                    enter-to-class="transition-enter-to"
                                    leave-active-class="transition-leave-active"
                                    leave-from-class="transition-leave-from"
                                    leave-to-class="transition-leave-to">
                            <DisclosurePanel
                                class="origin-top-right absolute z-30 overflow-y-auto max-h-48 w-72 rounded-lg shadow-lg py-1 bg-primary ring-1 ring-black ring-opacity-5 focus:outline-none">
                                <div v-if="departments.length === 0">
                                    <span class="text-secondary p-1 ml-4 flex flex-nowrap">{{$t('No teams available for assignment')}}</span>
                                </div>
                                <div v-for="team in departments">
                                        <span class="flex "
                                              :class="[team.checked ? 'text-secondaryHover' : 'text-secondary', 'group flex items-center px-4 py-2 text-md subpixel-antialiased']">
                                            <input :key="team.name" v-model="team.checked" type="checkbox"
                                                   @change="teamChecked(team)"
                                                   class="input-checklist-dark mr-3"/>
                                            <TeamIconCollection class="h-9 w-9 rounded-full" :iconName="team.svg_name"/>
                                            <span class="ml-2">
                                            {{ team.name }}
                                            </span>
                                        </span>
                                </div>
                            </DisclosurePanel>
                        </transition>
                    </Disclosure>
                    <div class="pb-5 my-2 border-gray-200 sm:pb-0">
                        <h3 class="mt-6 mb-8 headline2">{{ $t('Define user permissions')}}</h3>
                        <div class="mb-8">
                            <div v-for="role in roles">
                                <div class="relative flex w-full">
                                    <div class="flex h-6 items-center">
                                        <input v-model="role.checked"
                                               @change="changeRole(role)"
                                               :name="role.translation_key"
                                               :id="role.translation_key"
                                               type="checkbox"
                                               class="input-checklist"
                                        />
                                    </div>
                                    <div class="ml-3 w-full text-sm flex items-center justify-between">
                                        <label :for="role.translation_key" class="font-medium text-gray-900 w-5/6">
                                            {{ $t(role.translation_key)}}
                                        </label>

                                        <ToolTipDefault :top="true" :tooltip-text="$t(role.tooltipKey)" />
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div v-if="!this.form.roles.includes('artwork admin')" class="pb-5 my-2 border-gray-200 sm:pb-0">
                        <div v-on:click="showPresets = !showPresets">
                            <h2 class="flex headline6Light cursor-pointer mb-2">
                                {{$t('Permission presets')}}
                                <IconChevronUp stroke-width="1.5" v-if="showPresets"
                                               class=" ml-1 mr-3 flex-shrink-0 mt-1 h-4 w-4"></IconChevronUp>
                                <IconChevronDown stroke-width="1.5" v-else class=" ml-1 mr-3 flex-shrink-0 mt-1 h-4 w-4"></IconChevronDown>
                            </h2>
                        </div>
                        <div class="mb-8 flex flex-col" v-if="showPresets">
                            <div v-if="permission_presets.length > 0"
                                 v-for="preset in permission_presets">
                                <div class="relative flex w-full mb-2">
                                    <div class="flex h-6 items-center">
                                        <input v-model="preset.checked"
                                               @change="usePreset(preset)"
                                               :id="preset.name"
                                               :name="preset.name"
                                               type="checkbox"
                                               class="input-checklist"
                                        />
                                    </div>
                                    <div class="ml-3 w-full text-sm flex items-center justify-between">
                                        <label :for="preset.name" class="font-medium text-gray-900 w-5/6">
                                            {{ preset.name }}
                                        </label>
                                    </div>
                                </div>
                            </div>
                            <div v-else
                                 class="xsLight">
                                {{ $t('No permission presets have been created yet.')}}
                            </div>
                        </div>
                    </div>
                    <div v-if="!this.form.roles.includes('artwork admin')">
                        <div v-on:click="showUserPermissions = !showUserPermissions">
                            <h2 class="flex headline6Light cursor-pointer mb-2">
                                {{$t('User permissions')}}
                                <IconChevronUp stroke-width="1.5" v-if="showUserPermissions"
                                               class=" ml-1 mr-3 flex-shrink-0 mt-1 h-4 w-4"></IconChevronUp>
                                <IconChevronDown stroke-width="1.5" v-else class=" ml-1 mr-3 flex-shrink-0 mt-1 h-4 w-4"></IconChevronDown>
                            </h2>
                        </div>
                        <div v-if="showUserPermissions && this.form.role !== 'admin'"
                             class="flex flex-col">
                            <div v-for="(group, groupName) in this.computedGroupedPermissions"
                                 v-show="group.shown"
                            >
                                <div class="flex items-center justify-between">
                                    <h3 class="headline6Light mb-2 mt-3">{{ groupName }}</h3>
                                    <div class="text-xs underline text-artwork-buttons-create cursor-pointer" @click="checkOrUncheckAllPermissionsOfGroup(group)">
                                        {{ group.permissions.some(permission => permission.checked) ? $t('Deselect all') : $t('Select all') }}
                                    </div>
                                </div>
                                <div class="relative w-full flex items-center mb-2" v-for="(permission, index) in group.permissions" :key=index>
                                    <div class="relative flex w-full">
                                        <div class="flex h-6 items-center">
                                            <input v-model="permission.checked"
                                                   @change="changePermission(permission)"
                                                   :id="permission.translation_key"
                                                   :name="permission.translation_key"
                                                   type="checkbox"
                                                   class="input-checklist"
                                            />
                                        </div>
                                        <div class="ml-3 w-full text-sm flex items-center justify-between">
                                            <label :for="permission.translation_key" class="font-medium text-gray-900 w-5/6">
                                                {{ $t(permission.translation_key)}}
                                            </label>

                                            <ToolTipDefault :top="true" :tooltip-text="$t(permission.tooltipKey)" />
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="w-full items-center text-center">
                    <FormButton
                        class="mt-5"
                        @click="addUser"
                        :disabled="form.processing || (form.user_emails.length === 0)"
                        :text="$t('Invite')"
                    />
                </div>
            </div>

        </BaseModal>
</template>
<script>

import Permissions from "@/Mixins/Permissions.vue";
import JetDialogModal from '@/Jetstream/DialogModal.vue'
import JetInputError from '@/Jetstream/InputError.vue'
import {XIcon} from "@heroicons/vue/outline";
import {CheckIcon, ChevronDownIcon, ChevronUpIcon, XCircleIcon} from '@heroicons/vue/solid'
import Checkbox from "@/Layouts/Components/Checkbox.vue";
import TeamIconCollection from "@/Layouts/Components/TeamIconCollection.vue";
import {Disclosure, DisclosureButton, DisclosurePanel} from "@headlessui/vue";
import SvgCollection from "@/Layouts/Components/SvgCollection.vue";
import {useForm} from "@inertiajs/vue3";
import AddButtonSmall from "@/Layouts/Components/General/Buttons/AddButtonSmall.vue";
import FormButton from "@/Layouts/Components/General/Buttons/FormButton.vue";
import IconLib from "@/Mixins/IconLib.vue";
import ToolTipDefault from "@/Components/ToolTips/ToolTipDefault.vue";
import BaseModal from "@/Components/Modals/BaseModal.vue";
import TextInputComponent from "@/Components/Inputs/TextInputComponent.vue";

export default {
    name: "InviteUsersModal",
    mixins: [Permissions, IconLib],
    components: {
        TextInputComponent,
        BaseModal,
        ToolTipDefault,
        FormButton,
        AddButtonSmall,
        JetDialogModal,
        JetInputError,
        XIcon,
        ChevronDownIcon,
        ChevronUpIcon,
        Checkbox,
        CheckIcon,
        XCircleIcon,
        TeamIconCollection,
        Disclosure,
        DisclosureButton,
        DisclosurePanel,
        SvgCollection
    },
    props: {
        show: Boolean,
        closeModal: Function,
        all_permissions: Object,
        departments: Array,
        roles: Array,
        permission_presets: Array,
        users: Array,
        invitedUsers: Array,
    },
    data() {
        return {
            showUserPermissions: true,
            addingUser: false,
            deletingUser: false,
            showSuccessModal: false,
            userToDelete: {},
            emailInput: "",
            showSearchbar: false,
            user_query: "",
            user_search_results: [],
            showPresets: true,
            form: useForm({
                user_emails: [],
                permissions: [],
                departments: [],
                roles: [],
            }),
            showGlobalRoles: true,
            showInvalidEmailErrorText: false,
            usedPermissionPresets: [],
            helpText: "",
        }
    },
    computed: {
        errors() {
            return this.$page.props.errors;
        },
        computedGroupedPermissions() {
            let groupedPermissions = {};

            for (const [group, permissions] of Object.entries(this.all_permissions)) {
                groupedPermissions[group] = {
                    shown: true,
                    permissions: []
                };

                permissions.forEach((permission) => {
                    //permissions depending on specific logic to be displayed
                    if (permission.name === 'can view and delete sage100-api-data') {
                        //this permission is only added when sage api is enabled
                        if (this.$page.props.sageApiEnabled) {
                            groupedPermissions[group].permissions.push(permission);
                        }
                        return;
                    }

                    permission.checked = false;
                    //other permissions are pushed anytime
                    groupedPermissions[group].permissions.push(permission);
                });

                //groups are only shown when there are permissions to display
                groupedPermissions[group].shown = groupedPermissions[group].permissions.length > 0;
            }

            return groupedPermissions;
        }
    },
    updated() {
        //if component is updated set permissions to checked if they are contained in form
        Object.values(this.all_permissions).forEach((permissions) => {
            permissions.forEach((permission) => {
                permission.checked = this.form.permissions.includes(permission.name);
            });
        });

        //if there was a permission_preset used set it to checked
        this.usedPermissionPresets.forEach((usedPreset) => {
            this.permission_presets.forEach((permissionPreset) => {
                if (usedPreset.id === permissionPreset.id) {
                    permissionPreset.checked = true;
                }
            });
        });
    },
    methods: {
        checkOrUncheckAllPermissionsOfGroup(group) {
            // check if some permissions are already selected in the group and if so deselect them
            if (group.permissions.some(permission => this.form.permissions.includes(permission.name))) {
                group.permissions.forEach(permission => {
                    this.form.permissions = this.form.permissions.filter(permissionName => permissionName !== permission.name);
                    permission.checked = false;
                });
            } else {
                // select all permissions
                group.permissions.forEach(permission => {
                    this.form.permissions.push(permission.name);
                    permission.checked = true;
                });
            }
        },
        closeUserModal(bool){
            this.uncheckRolesAndPermissions();
            this.addingUser = false;
            this.emailInput = "";
            this.form.user_emails = [];
            this.form.permissions = [];
            this.form.departments = [];
            this.form.roles = [];
            this.departments.forEach((team) => {
                team.checked = false;
            })
            this.closeModal(bool);
        },
        addEmailToInvitationArray() {
            const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
            if (!emailRegex.test(this.emailInput)) {
                this.showInvalidEmailErrorText = true;
                return;
            }

            if (this.form.user_emails?.includes(this.emailInput)) {
                this.helpText = this.$t('This e-mail address already exists in the system. {0}', [this.emailInput]);
                return;
            }

            // check if email is already in users
            if (this.users) {
                const user = this.users.find(user => user.email === this.emailInput);

                if (user) {
                    this.helpText = this.$t('This e-mail address already exists in the system. {0}', [this.emailInput]);
                    return;
                }
            }

            this.showInvalidEmailErrorText = false;
            this.form.user_emails.push(this.emailInput);
            this.emailInput = "";
        },
        deleteEmailFromInvitationArray(index) {
            this.form.user_emails.splice(index, 1);
        },
        teamChecked(team) {
            if (team.checked) {
                this.form.departments.push(team);
            } else {
                const spliceIndex = this.form.departments.findIndex(teamToSplice => {
                    return team.id === teamToSplice.id
                })
                this.form.departments.splice(spliceIndex, 1);
            }
        },
        changeRole(role) {
            if (role.checked) {
                this.form.roles.push(role.name);
                return;
            }

            this.form.roles = this.form.roles.filter(permissionName => permissionName !== role.name);
        },
        changePermission(permission) {
            if (permission.checked) {
                this.form.permissions.push(permission.name);
            } else {
                this.form.permissions = this.form.permissions.filter(
                    (permissionName) => permissionName !== permission.name
                );
            }
        },
        usePreset(permissionPreset) {
            //Check/Uncheck the permissions based on the given permissionPreset
            Object.values(this.all_permissions).forEach((permissions) => {
                permissions.forEach((permission) => {
                    if (permissionPreset.permissions.includes(permission.id)) {
                        permission.checked = permissionPreset.checked;
                        if (permission.checked){
                            this.form.permissions.push(permission.name);
                        } else {
                            this.form.permissions = this.form.permissions.filter(
                                (permissionName) => permissionName !== permission.name
                            );
                        }
                    }
                });
            });

            //append used preset to array, if there is an backend error it will get checked again
            //see update lifecycle-hook
            if (permissionPreset.checked) {
                this.usedPermissionPresets.push(permissionPreset);
            } else {
                this.usedPermissionPresets = this.usedPermissionPresets.filter(
                    (usedPermissionPreset) => usedPermissionPreset.id !== permissionPreset.id
                );
            }
        },
        addUser() {
            this.form.post(
                route('invitations.store'),
                {
                    onSuccess: () => {
                        this.closeUserModal(true);
                        this.emailInput = "";
                        this.form.user_emails = [];
                        this.form.permissions = [];
                        this.form.departments = [];
                        this.form.role = '';
                        this.departments.forEach((team) => {
                            team.checked = false;
                        })
                    }
                }
            );
        },
        uncheckRolesAndPermissions() {
            this.roles.forEach((role) => {
                role.checked = false;
            })

            this.usedPermissionPresets = [];
            this.permission_presets.forEach((permission) => {
                permission.checked = false;
            })
        },
    },
}
</script>
