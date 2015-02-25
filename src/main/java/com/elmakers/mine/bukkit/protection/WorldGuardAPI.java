package com.elmakers.mine.bukkit.protection;

import com.elmakers.mine.bukkit.api.spell.SpellTemplate;
import com.sk89q.worldguard.bukkit.RegionContainer;
import com.sk89q.worldguard.bukkit.WorldGuardPlugin;
import com.sk89q.worldguard.domains.Association;
import com.sk89q.worldguard.protection.ApplicableRegionSet;
import com.sk89q.worldguard.protection.association.Associables;
import com.sk89q.worldguard.protection.association.RegionAssociable;
import com.sk89q.worldguard.protection.flags.DefaultFlag;
import com.sk89q.worldguard.protection.flags.StateFlag;
import com.sk89q.worldguard.protection.managers.RegionManager;
import org.apache.commons.lang.StringUtils;
import org.bukkit.Location;
import org.bukkit.block.Block;
import org.bukkit.entity.Player;
import org.bukkit.plugin.Plugin;

public class WorldGuardAPI {
	private WorldGuardPlugin worldGuard = null;
    private WGCustomFlagsManager customFlags = null;

	public boolean isEnabled() {
		return worldGuard != null;
	}
	
	public WorldGuardAPI(Plugin plugin) {
        if (plugin instanceof WorldGuardPlugin) {
            worldGuard = (WorldGuardPlugin)plugin;
            try {
                Plugin customFlagsPlugin = plugin.getServer().getPluginManager().getPlugin("WGCustomFlags");
                if (customFlagsPlugin != null) {
                    customFlags = new WGCustomFlagsManager(customFlagsPlugin);
                }
            } catch (Throwable ex) {
            }

            if (customFlags != null) {
                plugin.getLogger().info("WGCustomFlags found, adding allowed-spells and blocked-spells flags");
            }
        }
	}

    protected RegionAssociable getAssociable(Player player) {
        RegionAssociable associable;
        if (player == null) {
            associable = Associables.constant(Association.NON_MEMBER);
        } else {
            associable = worldGuard.wrapPlayer(player);
        }

        return associable;
    }
	
	public boolean isPVPAllowed(Player player, Location location) {
		if (worldGuard == null || location == null) return true;
				 
		RegionManager regionManager = worldGuard.getRegionManager(location.getWorld());
        if (regionManager == null) return true;

		ApplicableRegionSet checkSet = regionManager.getApplicableRegions(location);
		if (checkSet == null) return true;


		return checkSet.queryState(getAssociable(player), DefaultFlag.PVP) != StateFlag.State.DENY;
	}

	public boolean hasBuildPermission(Player player, Block block) {
		if (block != null && worldGuard != null) {
            RegionContainer container = worldGuard.getRegionContainer();
			return container.createQuery().testState(block.getLocation(), getAssociable(player), DefaultFlag.BUILD);
		}

		return true;
	}

    public boolean hasCastPermission(Player player, SpellTemplate spell) {
        if (player != null && worldGuard != null && customFlags != null)
        {
            Location location = player.getLocation();
            RegionManager regionManager = worldGuard.getRegionManager(location.getWorld());
            if (regionManager == null) return true;

            ApplicableRegionSet checkSet = regionManager.getApplicableRegions(location);
            if (checkSet == null) return true;

            if (checkSet.size() > 0 && checkSet.isOwnerOfAll(worldGuard.wrapPlayer(player)))
            {
                return true;
            }

           return customFlags.canCast(getAssociable(player), checkSet, spell);
        }

        return true;
    }

    public boolean hasCastPermissionOverride(Player player, SpellTemplate spell) {
        if (player != null && worldGuard != null && customFlags != null)
        {
            Location location = player.getLocation();
            RegionManager regionManager = worldGuard.getRegionManager(location.getWorld());
            if (regionManager == null) {
                return false;
            }

            ApplicableRegionSet checkSet = regionManager.getApplicableRegions(location);
            if (checkSet == null) {
                return false;
            }

            return customFlags.canOverrideCast(getAssociable(player), checkSet, spell);
        }
        return false;
    }
}
